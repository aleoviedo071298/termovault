#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# [N-01] Fase 2 — Revocar las reglas 0.0.0.0/0 (puerto 80 y 443) del SG.
#
# IMPORTANTE: ejecutar SOLO después de:
#   1. Haber corrido n01-sg-add-cloudflare.sh (que agrega las reglas de CF).
#   2. Verificar que https://termovault.com.ar responde 200 vía Cloudflare.
#
# Profile esperado: termovault-sg-ops.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

SG="sg-0c0bcdc8a4a831df1"
PROFILE="termovault-sg-ops"
REGION="us-east-2"

# Buscamos dinámicamente las reglas 0.0.0.0/0 ingress (puertos 80/443)
# en lugar de hardcodear los rule-ids, por si fueron re-creadas.
echo "── Buscando reglas 0.0.0.0/0 ingress en $SG …"
RULE_IDS=$(aws ec2 describe-security-group-rules \
  --profile "$PROFILE" --region "$REGION" \
  --filter "Name=group-id,Values=$SG" \
  --query 'SecurityGroupRules[?IsEgress==`false` && CidrIpv4==`0.0.0.0/0` && (FromPort==`80` || FromPort==`443`)].SecurityGroupRuleId' \
  --output text)

if [ -z "$RULE_IDS" ]; then
  echo "   no hay reglas 0.0.0.0/0 ingress (80/443). Nada que revocar."
  exit 0
fi

echo "   IDs a revocar: $RULE_IDS"
for ID in $RULE_IDS; do
  echo "── revocando $ID …"
  aws ec2 revoke-security-group-ingress \
    --profile "$PROFILE" --region "$REGION" \
    --group-id "$SG" \
    --security-group-rule-ids "$ID" \
    --output text | head -3
done

echo
echo "✅ Reglas 0.0.0.0/0 eliminadas. Origen accesible solo desde Cloudflare + IP admin."
