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
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# 旋转动画
spinner() {
    local pid=$1
    local delay=0.15
    local spinstr='⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏'
    while ps -p $pid > /dev/null 2>&1; do
        local temp=${spinstr#?}
        printf " ${CYAN}%s${NC}  " "$spinstr"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
        printf "\b\b\b\b"
    done
    printf "    \b\b\b\b"
}

# 进度条
progress_bar() {
    local current=$1
    local total=$2
    local width=30
    local percent=$((current * 100 / total))
    local filled=$((current * width / total))
    local empty=$((width - filled))
    printf "\r  ${YELLOW}[预拉取]${NC} [${GREEN}%s${NC}%s] ${percent}%% (%d/%d)" \
        "$(printf '#%.0s' $(seq 1 $filled 2>/dev/null) 2>/dev/null || echo "$(printf '%*s' $filled | tr ' ' '#')")" \
        "$(printf '%*s' $empty)" \
        "$current" "$total"
}

clear_line() {
    printf "\r%*s\r" 80 ""
}

echo -e "${GREEN}${BOLD}========================================"
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
echo -e "${GREEN}[✓]${NC} Docker 环境就绪"

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

GRAFANA_PORT=3001
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

# ---- 阶段 1/3: 并行预拉取基础镜像 ----
echo ""
echo -e "${GREEN}${BOLD}[阶段 1/3] 预拉取基础镜像（并行下载）${NC}"
echo -e "  ${CYAN}这一步会从镜像仓库拉取所有需要的基础镜像，重复部署会很快命中缓存${NC}"

BASE_IMAGES=(
    "node:20-alpine"
    "composer:2"
    "php:8.2-fpm-alpine"
    "nginx:1.25-alpine"
    "redis:7-alpine"
    "mysql:8.0"
    "prom/prometheus:latest"
    "grafana/grafana:latest"
    "prom/node-exporter:latest"
    "oliver006/redis_exporter:latest"
    "prom/mysqld_exporter:latest"
)

PULL_PIDS=()
for img in "${BASE_IMAGES[@]}"; do
    docker pull "$img" >/dev/null 2>&1 &
    PULL_PIDS+=($!)
done

TOTAL=${#PULL_PIDS[@]}
DONE=0
while [ $DONE -lt $TOTAL ]; do
    DONE=0
    for pid in "${PULL_PIDS[@]}"; do
        if ! kill -0 $pid 2>/dev/null; then
            DONE=$((DONE + 1))
        fi
    done
    progress_bar $DONE $TOTAL
    sleep 1
done
clear_line
echo -e "  ${GREEN}[✓] 基础镜像预拉取完成 (${TOTAL} 个)${NC}"

# ---- 阶段 2/3: 构建应用镜像 ----
echo ""
echo -e "${GREEN}${BOLD}[阶段 2/3] 构建应用镜像（前端 + Composer + PHP）${NC}"
echo -e "  ${CYAN}⏳ 首次部署约需 5-10 分钟，代码无修改时通常 < 30 秒（命中缓存）${NC}"
echo -e "  ${CYAN}每完成一个构建步骤会显示进度...${NC}"
echo ""

BUILD_START=$(date +%s)
docker compose --profile monitoring build --progress=auto 2>&1 | \
    grep -E "^#[0-9]+" | \
    while IFS= read -r line; do
        # 提取阶段号和描述
        if [[ "$line" =~ ^#([0-9]+)[[:space:]]+(.*)$ ]]; then
            step_num="${BASH_REMATCH[1]}"
            step_desc="${BASH_REMATCH[2]}"
            # 过滤掉无意义的 Docker 空行
            if [[ "$step_desc" =~ ^(DONE|CACHED|transferring|sha256|sha512) ]] || [[ -z "$step_desc" ]]; then
                continue
            fi
            ELAPSED=$(( $(date +%s) - BUILD_START ))
            printf "  ${CYAN}▸${NC} [阶段 %s] %s ${YELLOW}(%ds)${NC}\n" "$step_num" "$step_desc" "$ELAPSED"
        fi
    done

if [ ${PIPESTATUS[0]} -ne 0 ]; then
    echo ""
    echo -e "${RED}[✗] 镜像构建失败！${NC}"
    echo -e "${YELLOW}请手动执行以下命令查看详细错误：${NC}"
    echo -e "  docker compose build --progress=plain"
    exit 1
fi

BUILD_ELAPSED=$(( $(date +%s) - BUILD_START ))
echo ""
echo -e "  ${GREEN}[✓] 镜像构建完成 (耗时 ${BUILD_ELAPSED} 秒)${NC}"

# ---- 阶段 3/3: 启动服务 ----
echo ""
echo -e "${GREEN}${BOLD}[阶段 3/3] 启动所有容器${NC}"
docker compose --profile monitoring up -d 2>&1 | tail -15

# ---- 等待服务就绪 ----
echo ""
echo -e "${GREEN}[等待] 服务启动中...${NC}"

WAITED=0
MAX_WAIT=120
APP_READY=0
while [ $WAITED -lt $MAX_WAIT ]; do
    # 检查 app 容器状态
    if docker compose ps app 2>/dev/null | grep -q "Up"; then
        # 进一步检查 health 状态
        HEALTH=$(docker compose ps app --format json 2>/dev/null | grep -o '"Health":"[^"]*"' | cut -d'"' -f4 || echo "starting")
        if [ "$HEALTH" = "healthy" ] || [ "$HEALTH" = "" ]; then
            APP_READY=1
            break
        fi
    fi
    printf "\r  ${YELLOW}⏳ 等待 app 启动... (%d/%d 秒)${NC} " $WAITED $MAX_WAIT
    sleep 2
    WAITED=$((WAITED + 2))
done
echo ""

if [ $APP_READY -eq 1 ]; then
    echo -e "  ${GREEN}[✓] app 启动完成 (等待 ${WAITED} 秒)${NC}"
else
    echo -e "  ${YELLOW}[!] app 启动超时（已等 ${WAITED} 秒），继续检查其他服务...${NC}"
fi

# 检查核心服务
echo ""
echo -e "${BLUE}[检查] 服务状态...${NC}"
for svc in app mysql redis horizon reverb scheduler; do
    if docker compose ps "$svc" 2>/dev/null | grep -q "Up"; then
        echo -e "  ${GREEN}[✓] $svc${NC}"
    else
        echo -e "  ${RED}[✗] $svc 异常${NC}"
    fi
done

# 检查监控（可选）
if docker compose --profile monitoring ps prometheus 2>/dev/null | grep -q "Up"; then
    echo -e "  ${GREEN}[✓] prometheus (监控已启用)${NC}"
fi
if docker compose --profile monitoring ps grafana 2>/dev/null | grep -q "Up"; then
    echo -e "  ${GREEN}[✓] grafana (监控已启用)${NC}"
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
echo -e "${GREEN}${BOLD}========================================"
echo "  部署完成!"
echo -e "========================================${NC}"
echo ""
echo -e "  应用主页:  ${BLUE}http://${SERVER_IP}:8080${NC}"
echo -e "  Horizon:   ${BLUE}http://${SERVER_IP}:8080/horizon${NC}"
echo ""
echo -e "  监控面板 (Grafana):"
echo -e "    ${BLUE}http://${SERVER_IP}:3001${NC} (admin / admin123)"
echo -e "    ${YELLOW}⚠ 仅限你的 IP 访问（安全组白名单）${NC}"
echo ""
echo -e "  常用命令:"
echo "    查看日志   docker compose logs -f app"
echo "    重启服务   docker compose restart app"
echo "    停止服务   docker compose down"
echo ""
echo -e "  数据库密码: ${YELLOW}${DB_PASS}${NC} (已写入 .env.production)"
echo ""
