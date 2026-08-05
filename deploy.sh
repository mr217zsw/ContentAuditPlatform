#!/bin/bash
# ============================================================
# Content Audit Platform - 服务器一键部署
# 用法: bash deploy.sh
# ============================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${GREEN}========================================"
echo "  Content Audit Platform 部署"
echo -e "========================================${NC}"

# ---- 检查 Docker ----
if ! command -v docker &>/dev/null; then
    echo -e "${RED}[错误] 未检测到 Docker，请先安装: curl -fsSL https://get.docker.com | bash${NC}"
    exit 1
fi
if ! docker compose version &>/dev/null; then
    echo -e "${RED}[错误] 需要 Docker Compose v2${NC}"
    exit 1
fi
echo -e "${GREEN}[✓] Docker 环境就绪${NC}"

# ---- 端口检查 ----
echo ""
echo -e "${BLUE}[检查] 端口占用...${NC}"
for PORT in 8080 8081 3307 6379; do
    if ss -tlnp 2>/dev/null | grep -q ":$PORT "; then
        echo -e "  ${YELLOW}[!] 端口 $PORT 已被占用${NC}"
    else
        echo -e "  ${GREEN}[✓] 端口 $PORT 空闲${NC}"
    fi
done

# ---- 自动生成配置 ----
if [ ! -f .env.production ]; then
    echo ""
    echo -e "${BLUE}[初始化] 自动生成 .env.production ...${NC}"

    # 生成随机密钥
    REVERB_ID=$(openssl rand -hex 16 2>/dev/null || cat /dev/urandom | tr -dc 'a-f0-9' | head -c 32)
    REVERB_KEY=$(openssl rand -hex 16 2>/dev/null || cat /dev/urandom | tr -dc 'a-f0-9' | head -c 32)
    REVERB_SECRET=$(openssl rand -hex 32 2>/dev/null || cat /dev/urandom | tr -dc 'a-f0-9' | head -c 64)
    DB_PASS=$(openssl rand -hex 12 2>/dev/null || cat /dev/urandom | tr -dc 'a-f0-9' | head -c 24)

    cat > .env.production << EOF
APP_NAME="ContentAuditPlatform"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://YOUR_SERVER_IP

APP_KEY=

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=content_audit
DB_USERNAME=audit_user
DB_PASSWORD=${DB_PASS}

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

BROADCAST_CONNECTION=reverb

REVERB_APP_ID=${REVERB_ID}
REVERB_APP_KEY=${REVERB_KEY}
REVERB_APP_SECRET=${REVERB_SECRET}
REVERB_HOST=reverb
REVERB_PORT=8081
REVERB_SCHEME=http

HORIZON_DOMAIN=null
HORIZON_PATH=horizon

FILESYSTEM_DISK=local

APP_PORT=8080
MYSQL_PORT=3307
REDIS_PORT=6379
REVERB_PORT=8081

GRAFANA_USER=admin
GRAFANA_PASSWORD=admin123
EOF

    echo -e "${GREEN}[✓] .env.production 已生成${NC}"
    echo ""
    echo -e "${YELLOW}  ⚠ 请务必修改以下两项:${NC}"
    echo -e "    vim .env.production"
    echo -e "    1. APP_URL → 改为服务器实际 IP 或域名"
    echo -e "    2. GRAFANA_PASSWORD → 改为强密码"
    echo ""
    read -p "  修改完成后按回车继续部署..."
fi

# ---- 构建并启动 ----
echo ""
echo -e "${GREEN}[部署] 构建镜像并启动服务...${NC}"
docker compose up -d --build 2>&1 | tail -20

echo ""
echo -e "${GREEN}[等待] 服务初始化中 (约 30 秒)...${NC}"
sleep 15

# 检查核心服务
FAILED=0
for svc in app mysql redis; do
    STATUS=$(docker compose ps "$svc" --format json 2>/dev/null | grep -o '"Health":"[^"]*"' | cut -d'"' -f4 || echo "unknown")
    if [ "$STATUS" = "healthy" ] || docker compose ps "$svc" 2>/dev/null | grep -q "Up"; then
        echo -e "  ${GREEN}[✓] $svc${NC}"
    else
        echo -e "  ${RED}[✗] $svc 异常${NC}"
        FAILED=1
    fi
done

if [ $FAILED -eq 1 ]; then
    echo ""
    echo -e "${RED}部分服务未启动，查看日志: docker compose logs${NC}"
    exit 1
fi

# ---- 数据库迁移 ----
echo ""
echo -e "${GREEN}[迁移] 初始化数据库...${NC}"
docker compose exec -T app php artisan migrate --force 2>&1 || {
    echo -e "${YELLOW}[重试] 等待 MySQL 完全就绪...${NC}"
    sleep 20
    docker compose exec -T app php artisan migrate --force
}

# ---- 完成 ----
SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}' || echo "服务器IP")
echo ""
echo -e "${GREEN}========================================"
echo "  部署完成!"
echo -e "========================================${NC}"
echo ""
echo -e "  应用主页:  ${BLUE}http://${SERVER_IP}:8080${NC}"
echo -e "  Horizon:   ${BLUE}http://${SERVER_IP}:8080/horizon${NC}"
echo -e "  数据库密码: ${YELLOW}${DB_PASS}${NC} (已写入 .env.production)"
echo ""
echo -e "  常用命令:"
echo "    查看日志   docker compose logs -f app"
echo "    重启服务   docker compose restart app"
echo "    停止服务   docker compose down"
echo ""
echo -e "  开启监控 (可选):"
echo "    docker compose --profile monitoring up -d"
echo "    Grafana → http://${SERVER_IP}:3000 (admin / admin123)"
echo ""
