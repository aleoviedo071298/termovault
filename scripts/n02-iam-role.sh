#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# [N-02] FASE 1 — Crear IAM role mínimo para EC2 + asociar a la instancia.
#
# Este script NO toca el .env de la app. Solo prepara la infraestructura AWS
# para que la EC2 PUEDA usar un instance role. La validación y el cambio del
# .env se hacen en un segundo paso (n02-switch-to-role.sh), después de
# verificar que el role funciona.
#
# REQUISITOS:
#   - AWS CLI configurado con un perfil que tenga permisos para:
#       iam:CreateRole, iam:CreatePolicy, iam:AttachRolePolicy,
#       iam:CreateInstanceProfile, iam:AddRoleToInstanceProfile,
#       ec2:AssociateIamInstanceProfile, ec2:DescribeInstances
#   - jq (para parsear las respuestas)
#
# USO:
#   chmod +x scripts/n02-iam-role.sh
#   ./scripts/n02-iam-role.sh
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

# ─── CONFIG (ajustá si difiere) ────────────────────────────────────────────
AWS_PROFILE="${AWS_PROFILE:-default}"   # cambiá si usás otro perfil
AWS_REGION="us-east-2"                  # confirmado en el recon
INSTANCE_ID=""                          # se autodetecta abajo por IP pública
INSTANCE_PUBLIC_IP="${INSTANCE_PUBLIC_IP:?Set INSTANCE_PUBLIC_IP env var to the EC2's public IP}"
BUCKET="${BUCKET:?Set BUCKET env var to the target S3 bucket name}"
ROLE_NAME="${ROLE_NAME:?Set ROLE_NAME env var, e.g. ROLE_NAME=myapp-prod-ec2}"
POLICY_NAME="${POLICY_NAME:?Set POLICY_NAME env var, e.g. POLICY_NAME=myapp-prod-s3-access}"
PROFILE_NAME="${PROFILE_NAME:-$ROLE_NAME}"  # mismo nombre que el role por convención
# ───────────────────────────────────────────────────────────────────────────

echo "▶ AWS profile: $AWS_PROFILE | región: $AWS_REGION"
echo "▶ Bucket objetivo: $BUCKET"
echo "▶ Role a crear:    $ROLE_NAME"
echo

# 0. Autodetectar el instance-id por IP pública (más seguro que hardcoded)
echo "── 0. Detectando instance-id por IP pública $INSTANCE_PUBLIC_IP …"
INSTANCE_ID=$(aws ec2 describe-instances \
  --profile "$AWS_PROFILE" --region "$AWS_REGION" \
  --filters "Name=ip-address,Values=$INSTANCE_PUBLIC_IP" \
  --query 'Reservations[0].Instances[0].InstanceId' --output text)

if [ -z "$INSTANCE_ID" ] || [ "$INSTANCE_ID" = "None" ]; then
  echo "❌ No encontré instance con IP pública $INSTANCE_PUBLIC_IP. Aborto."
  exit 1
fi
echo "   instance-id: $INSTANCE_ID"

# Verificar que NO tiene ya un instance profile (recon dijo que no)
EXISTING_PROFILE=$(aws ec2 describe-instances \
  --profile "$AWS_PROFILE" --region "$AWS_REGION" \
  --instance-ids "$INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].IamInstanceProfile.Arn' --output text 2>/dev/null || echo "None")
if [ "$EXISTING_PROFILE" != "None" ] && [ -n "$EXISTING_PROFILE" ]; then
  echo "⚠️  La instancia YA tiene un instance profile asociado: $EXISTING_PROFILE"
  echo "    Revisalo antes de continuar. Aborto por seguridad."
  exit 1
fi
echo "   ✅ instancia sin instance profile (esperado)"

# 1. Trust policy: permite que EC2 asuma el role
echo
echo "── 1. Creando trust policy para EC2 …"
cat > /tmp/trust-ec2.json <<'JSON'
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": {"Service": "ec2.amazonaws.com"},
    "Action": "sts:AssumeRole"
  }]
}
JSON

# 2. Crear el role
echo "── 2. Creando IAM role: $ROLE_NAME …"
if aws iam get-role --profile "$AWS_PROFILE" --role-name "$ROLE_NAME" >/dev/null 2>&1; then
  echo "   ya existe, salteando creación"
else
  aws iam create-role \
    --profile "$AWS_PROFILE" \
    --role-name "$ROLE_NAME" \
    --assume-role-policy-document file:///tmp/trust-ec2.json \
    --description "Acceso S3 mínimo para la app TermoVault en producción" \
    --tags Key=Project,Value=termovault Key=Env,Value=prod \
    > /dev/null
  echo "   ✅ role creado"
fi

# 3. Policy mínima — SOLO el bucket termovault-prod
echo "── 3. Creando policy mínima para S3 ($BUCKET) …"
cat > /tmp/policy-s3.json <<JSON
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ListBucket",
      "Effect": "Allow",
      "Action": ["s3:ListBucket", "s3:GetBucketLocation"],
      "Resource": "arn:aws:s3:::${BUCKET}"
    },
    {
      "Sid": "ObjectCRUD",
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject",
        "s3:GetObjectAcl",
        "s3:PutObjectAcl"
      ],
      "Resource": "arn:aws:s3:::${BUCKET}/*"
    }
  ]
}
JSON

ACCOUNT_ID=$(aws sts get-caller-identity --profile "$AWS_PROFILE" --query Account --output text)
POLICY_ARN="arn:aws:iam::${ACCOUNT_ID}:policy/${POLICY_NAME}"

if aws iam get-policy --profile "$AWS_PROFILE" --policy-arn "$POLICY_ARN" >/dev/null 2>&1; then
  echo "   policy ya existe ($POLICY_ARN), salteando"
else
  aws iam create-policy \
    --profile "$AWS_PROFILE" \
    --policy-name "$POLICY_NAME" \
    --policy-document file:///tmp/policy-s3.json \
    --description "S3 RW solo en el bucket ${BUCKET}" \
    > /dev/null
  echo "   ✅ policy creada: $POLICY_ARN"
fi

# 4. Attach policy al role
echo "── 4. Asociando policy al role …"
aws iam attach-role-policy \
  --profile "$AWS_PROFILE" \
  --role-name "$ROLE_NAME" \
  --policy-arn "$POLICY_ARN"
echo "   ✅ policy asociada"

# 5. Instance profile (envoltorio del role para EC2)
echo "── 5. Creando instance profile $PROFILE_NAME …"
if aws iam get-instance-profile --profile "$AWS_PROFILE" --instance-profile-name "$PROFILE_NAME" >/dev/null 2>&1; then
  echo "   instance profile ya existe"
else
  aws iam create-instance-profile \
    --profile "$AWS_PROFILE" \
    --instance-profile-name "$PROFILE_NAME" \
    > /dev/null
  echo "   ✅ instance profile creado"
fi

# Asociar role al instance profile (idempotente)
CURRENT_ROLES=$(aws iam get-instance-profile \
  --profile "$AWS_PROFILE" \
  --instance-profile-name "$PROFILE_NAME" \
  --query 'InstanceProfile.Roles[*].RoleName' --output text)
if echo "$CURRENT_ROLES" | grep -q "$ROLE_NAME"; then
  echo "   role ya asociado al instance profile"
else
  aws iam add-role-to-instance-profile \
    --profile "$AWS_PROFILE" \
    --instance-profile-name "$PROFILE_NAME" \
    --role-name "$ROLE_NAME"
  echo "   ✅ role agregado al instance profile"
  echo "   esperando 10s para propagación …"
  sleep 10
fi

# 6. Asociar instance profile a la EC2
echo "── 6. Asociando instance profile a la EC2 ($INSTANCE_ID) …"
aws ec2 associate-iam-instance-profile \
  --profile "$AWS_PROFILE" --region "$AWS_REGION" \
  --instance-id "$INSTANCE_ID" \
  --iam-instance-profile "Name=${PROFILE_NAME}" \
  > /dev/null
echo "   ✅ asociado"
echo
echo "   esperando 15s para que IMDS exponga las credenciales …"
sleep 15

# 7. Verificación final
echo
echo "── 7. Verificación final …"
echo "   IAM Instance Profile en la EC2:"
aws ec2 describe-instances \
  --profile "$AWS_PROFILE" --region "$AWS_REGION" \
  --instance-ids "$INSTANCE_ID" \
  --query 'Reservations[0].Instances[0].IamInstanceProfile.Arn' --output text

echo
echo "✅ N-02 FASE 1 COMPLETA."
echo
echo "Lo que falta (FASE 2, lo hago yo por SSH):"
echo "  1. Test desde la EC2: 'aws s3 ls s3://${BUCKET}/ --no-sign-request' usando IMDS"
echo "  2. Comentar AWS_ACCESS_KEY_ID y AWS_SECRET_ACCESS_KEY en .env (backup primero)"
echo "  3. config:cache + reload php-fpm"
echo "  4. Smoke test: subir y descargar un archivo en la app"
echo "  5. Una vez OK, vos rotás (deshabilitás) las keys viejas en IAM"
echo
echo "Archivos generados (revisables):"
echo "  /tmp/trust-ec2.json  /tmp/policy-s3.json"
