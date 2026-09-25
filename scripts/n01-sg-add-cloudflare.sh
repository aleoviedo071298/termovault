#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# [N-01] Fase 1 — Agregar reglas de Cloudflare + IP admin al SG de la EC2.
#
# Este script SOLO AGREGA reglas. NO BORRA NADA. Las reglas 0.0.0.0/0 viejas
# siguen ahí; se eliminan en un script separado (n01-sg-remove-open.sh) tras
# verificar que el sitio sigue OK.
#
# Profile esperado: termovault-sg-ops (policy mínima sobre el SG pasado en $SG).
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

SG="${SG:?Set SG env var to the EC2 security group id, e.g. SG=sg-xxxxxxxx}"
PROFILE="termovault-sg-ops"
REGION="us-east-2"
ADMIN_IP="${ADMIN_IP:?Set ADMIN_IP env var, e.g. ADMIN_IP=1.2.3.4/32 ./scripts/n01-sg-add-cloudflare.sh}"

CF_V4=(
  173.245.48.0/20  103.21.244.0/22  103.22.200.0/22  103.31.4.0/22
  141.101.64.0/18  108.162.192.0/18 190.93.240.0/20  188.114.96.0/20
  197.234.240.0/22 198.41.128.0/17  162.158.0.0/15   104.16.0.0/13
  104.24.0.0/14    172.64.0.0/13    131.0.72.0/22
)
CF_V6=(
  2400:cb00::/32  2606:4700::/32 2803:f800::/32 2405:b500::/32
  2405:8100::/32  2a06:98c0::/29 2c0f:f248::/32
)

# Construye el array IpPermissions con todas las reglas de un puerto
build_permissions () {
  local port="$1"
  local ip_ranges=""
  local i=1
  for cidr in "${CF_V4[@]}"; do
    ip_ranges+="{\"CidrIp\":\"$cidr\",\"Description\":\"Cloudflare IPv4 ${i}/15\"},"
    ((i++))
  done
  ip_ranges+="{\"CidrIp\":\"$ADMIN_IP\",\"Description\":\"Admin debug\"}"

  local ipv6_ranges=""
  i=1
  for cidr in "${CF_V6[@]}"; do
    ipv6_ranges+="{\"CidrIpv6\":\"$cidr\",\"Description\":\"Cloudflare IPv6 ${i}/7\"}"
    [ $i -lt 7 ] && ipv6_ranges+=","
    ((i++))
  done

  echo "[{\"IpProtocol\":\"tcp\",\"FromPort\":${port},\"ToPort\":${port},\"IpRanges\":[${ip_ranges}],\"Ipv6Ranges\":[${ipv6_ranges}]}]"
}

for PORT in 443 80; do
  echo "── Autorizando puerto ${PORT} con $(( ${#CF_V4[@]} + ${#CF_V6[@]} + 1 )) orígenes …"
  PERMS=$(build_permissions "$PORT")
  aws ec2 authorize-security-group-ingress \
    --profile "$PROFILE" --region "$REGION" \
    --group-id "$SG" \
    --ip-permissions "$PERMS" \
    --output table | head -30
  echo
done

echo "✅ Fase 1 completada. Reglas 0.0.0.0/0 viejas todavía presentes."
echo "   Siguiente paso: verificar y luego correr n01-sg-remove-open.sh"
