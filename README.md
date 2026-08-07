# ContentAuditPlatform - 内容审核管理平台

基于 **Laravel 11 + Vue 3** 的企业级内容审核管理系统，提供从内容创作、多级审核流程、敏感词自动检测到内容发布/驳回的完整工作流。

---

## 目录

- [技术栈](#技术栈)
- [功能特性](#功能特性)
- [审核工作流](#审核工作流)
- [项目结构](#项目结构)
- [快速开始](#快速开始)
  - [Docker 一键部署](#docker-一键部署)
  - [本地开发](#本地开发)
- [角色权限](#角色权限)
- [API 文档](#api-文档)
- [数据库设计](#数据库设计)
- [部署架构](#部署架构)
- [监控](#监控)
- [CI/CD](#cicd)

---

## 技术栈

### 后端

| 技术 | 版本 | 用途 |
|------|------|------|
| **PHP** | `^8.2` | 运行环境 |
| **Laravel** | `^11.0` | 后端框架 |
| **Laravel Sanctum** | `^4.0` | SPA Token 认证 |
| **Laravel Horizon** | `^5.0` | 队列监控与管理 |
| **Laravel Reverb** | `^1.0` | 自托管 WebSocket 服务 |
| **Predis** | `^2.0` | Redis 客户端驱动 |
| **MySQL** | `8.0` | 关系型数据库 |
| **Redis** | `7.x` | 缓存 / 队列 / 广播驱动 |

### 前端

| 技术 | 版本 | 用途 |
|------|------|------|
| **Vue 3** | `^3.4.0` | 前端框架（Composition API） |
| **Vue Router** | `^4.3.0` | SPA 路由 |
| **Pinia** | `^2.1.0` | 状态管理 |
| **Axios** | `^1.7.0` | HTTP 客户端 |
| **Laravel Echo + PusherJS** | `^1.16.0` / `^8.4.0` | WebSocket 客户端 |
| **Tailwind CSS** | `^3.4.0` | CSS 工具类框架 |
| **Vite** | `^5.4.0` | 构建工具 |

### 基础设施

| 技术 | 用途 |
|------|------|
| **Docker + Docker Compose** | 容器化编排（9 个服务） |
| **Nginx** (1.25-alpine) | Web 服务器 + API 限流 + 安全加固 |
| **Supervisor** | 容器内多进程管理 |
| **Prometheus + Grafana** | 全栈监控（自定义业务指标） |
| **GitLab CI** | 自动化测试、构建、部署 |

---

## 功能特性

### 核心功能

- **多级审核工作流**：编辑初审 → 主管审核 → 终审发布，支持任意环节驳回
- **敏感词检测引擎**：
  - 基于 UTF-8 大小写不敏感的实时匹配
  - 四级风险分类：`low`（低）→ `medium`（中）→ `high`（高）→ `forbidden`（禁止）
  - 实时检测 + 异步持久化命中记录
- **实时通知**：通过 WebSocket 推送审核状态变更，前端即时更新
- **RBAC 权限控制**：4 种角色精细控制接口和页面访问
- **审核日志追踪**：完整记录每一步审核操作、意见和稿件快照

### 安全特性

- Sanctum Token 认证
- Nginx 层 API 限流（10 req/s） + 登录限流（5 req/min）
- Content-Security-Policy 安全头
- X-Content-Type-Options / X-Frame-Options / X-XSS-Protection
- HSTS 强制 HTTPS（生产环境）
- CORS 限制

---

## 审核工作流

```
┌─────────┐    提交      ┌──────────┐   编辑通过    ┌──────────────┐   主管通过    ┌──────────────┐   终审通过    ┌──────────┐
│  草稿   │ ──────────→ │  待初审   │ ──────────→ │  编辑已通过   │ ──────────→ │  编辑已通过   │ ──────────→ │  已发布   │
│  draft  │             │ pending   │             │editor_approved│             │editor_approved│             │published │
└─────────┘             └──────────┘             └──────────────┘             └──────────────┘             └──────────┘
                              │                        │                            │
                              │       驳回              │        驳回                 │        驳回
                              └────────────────────────┴────────────────────────────┘
                                                    ↓
                                             ┌──────────┐
                                             │  已驳回   │
                                             │ rejected │
                                             └──────────┘
                                                  │
                                                  │ 作者编辑后重新提交
                                                  ↓
                                             ┌──────────┐
                                             │  待初审   │
                                             │ pending   │
                                             └──────────┘
```

- 每个审批节点对应一种角色：`editor` → `supervisor` → `final_approver`
- 任意阶段驳回后，稿件回到 `rejected` 状态，作者可编辑后重新提交

---

## 项目结构

```
ContentAuditPlatform/
├── app/                          # 应用核心代码
│   ├── Console/Kernel.php        # 定时任务调度
│   ├── Events/                   # WebSocket 事件
│   │   ├── ArticleSubmitted.php  # 稿件提交事件（通知编辑）
│   │   └── ArticleStatusChanged.php # 状态变更事件（通知作者和审核员）
│   ├── Http/
│   │   ├── Controllers/          # 控制器
│   │   │   ├── Api/              # API 控制器
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── ArticleController.php
│   │   │   │   ├── AuditController.php
│   │   │   │   ├── SensitiveWordController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── HealthController.php
│   │   │   │   └── MetricsController.php
│   │   │   └── Controller.php    # 基础控制器
│   │   ├── Middleware/
│   │   │   └── SecurityHeaders.php # 安全头中间件
│   │   └── Requests/             # 表单验证
│   ├── Jobs/
│   │   └── ProcessSensitiveWords.php # 异步敏感词检测任务
│   ├── Models/                   # Eloquent 模型
│   │   ├── User.php
│   │   ├── Article.php
│   │   ├── AuditLog.php
│   │   ├── SensitiveWord.php
│   │   └── SensitiveWordHit.php
│   └── Services/
│       └── SensitiveWordService.php # 敏感词检测引擎
├── config/                       # 应用配置
│   ├── auth.php                  # 认证配置（Sanctum + Guards）
│   ├── horizon.php               # 队列监控仪表板
│   ├── reverb.php                # WebSocket 服务配置
│   └── ...
├── database/
│   ├── migrations/               # 数据库迁移（6 张业务表）
│   └── seeders/
│       └── DatabaseSeeder.php    # 演示数据（5 用户 + 10 敏感词）
├── resources/
│   └── js/                       # Vue 3 前端源码
│       ├── app.js                # 入口（Vue + Router + Pinia + Echo）
│       ├── components/           # 公共组件
│       │   ├── DefaultLayout.vue # 侧边栏布局
│       │   ├── ArticleForm.vue   # 稿件编辑弹窗
│       │   └── StatusBadge.vue   # 状态标签
│       ├── composables/          # 组合式函数
│       │   ├── useSensitiveWordCheck.js # 实时敏感词检测
│       │   └── usePagination.js  # 分页逻辑
│       ├── pages/                # 页面组件
│       │   ├── Login.vue         # 登录页
│       │   ├── Dashboard.vue     # 工作台
│       │   ├── Articles.vue      # 稿件管理
│       │   ├── Audit.vue         # 审核中心
│       │   └── SensitiveWords.vue # 敏感词管理
│       └── stores/               # Pinia 状态
│           ├── auth.js
│           ├── article.js
│           ├── audit.js
│           └── dashboard.js
├── routes/
│   ├── api.php                   # API 路由（认证 + 业务 + 健康检查）
│   └── channels.php              # WebSocket 频道授权
├── docker/                       # Docker 相关配置
├── docker-compose.yml            # 服务编排（9 个容器）
├── Dockerfile                    # 多阶段构建（frontend → vendor → production）
├── deploy.sh                     # 一键部署脚本
├── .gitlab-ci.yml                # GitLab CI/CD 流水线
├── vite.config.js                # Vite 构建配置
├── tailwind.config.js            # Tailwind CSS 配置
└── composer.json                 # PHP 依赖管理
```

---

## 快速开始

### Docker 一键部署

```bash
# 1. 克隆项目
git clone <repo-url>
cd ContentAuditPlatform

# 2. 执行部署脚本（自动检测环境 + 生成配置 + 构建 + 启动 + 迁移）
sudo bash deploy.sh
```

脚本会自动完成：
- 检测 Docker 环境
- 生成 `.env.production`（随机 APP_KEY）
- 并行预拉取基础镜像
- 4 阶段 Docker 构建
- 数据库迁移
- 健康检查等待

### 本地开发

```bash
# 1. 安装 PHP 依赖
composer install

# 2. 配置环境变量
cp .env.example .env
php artisan key:generate

# 3. 安装前端依赖
npm install

# 4. 启动数据库（需要本地或 Docker MySQL + Redis）
# 如用 Docker 仅启动基础设施：
docker compose up -d mysql redis reverb

# 5. 数据库迁移 + 填充测试数据
php artisan migrate --seed

# 6. 启动开发服务
npm run dev                     # 前端 HMR
php artisan serve               # 后端 API（:8000）
php artisan horizon             # 队列处理器
php artisan reverb:start        # WebSocket 服务
```

> **演示账号**（密码均为 `{role}123`）：
> - `admin@audit.local` — 管理员
> - `editor@audit.local` / `editor2@audit.local` — 编辑
> - `supervisor@audit.local` — 主管
> - `final@audit.local` — 终审员

---

## 角色权限

| 角色 | 权限范围 |
|------|----------|
| **admin**（管理员） | 全部权限：管理敏感词、查看所有稿件和审核日志 |
| **editor**（编辑/作者） | 创建/编辑稿件、提交审核、初审通过/驳回 |
| **supervisor**（主管） | 对已通过初审的稿件进行二审 |
| **final_approver**（终审员） | 对主管通过的稿件进行终审并发布 |

---

## API 文档

### 认证

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/api/v1/auth/login` | 登录 | 否 |
| POST | `/api/v1/auth/logout` | 登出 | Sanctum |
| GET | `/api/v1/auth/me` | 当前用户信息 | Sanctum |

### 稿件管理

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/v1/articles` | 稿件列表（分页 + 状态筛选） | Sanctum |
| POST | `/api/v1/articles` | 创建稿件（自动敏感词检测） | Sanctum |
| GET | `/api/v1/articles/{id}` | 稿件详情（含审核日志） | Sanctum |
| PUT | `/api/v1/articles/{id}` | 更新稿件（仅 draft/rejected） | Sanctum |
| DELETE | `/api/v1/articles/{id}` | 删除稿件（仅 draft） | Sanctum |
| POST | `/api/v1/articles/{id}/submit` | 提交审核 | Sanctum |

### 审核中心

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/v1/audit/pending` | 待审核列表（按角色筛选） | Sanctum |
| POST | `/api/v1/audit/{id}/approve` | 通过审核 | Sanctum |
| POST | `/api/v1/audit/{id}/reject` | 驳回（需填写原因） | Sanctum |
| GET | `/api/v1/audit/{id}/logs` | 审核日志 | Sanctum |
| GET | `/api/v1/audit/history` | 我的审核历史 | Sanctum |

### 敏感词管理（仅 admin）

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/v1/sensitive-words` | 列表（按等级筛选） | Sanctum |
| POST | `/api/v1/sensitive-words` | 添加 | Sanctum |
| DELETE | `/api/v1/sensitive-words/{word}` | 删除 | Sanctum |
| POST | `/api/v1/sensitive-words/check` | 文本检测（调试） | Sanctum |

### 统计

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| GET | `/api/v1/dashboard/stats` | 待审核数、今日通过/驳回、月度总计、7天趋势 | Sanctum |

### 健康检查（公开）

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/health` | 完整健康检查（DB + Redis + Cache） |
| GET | `/api/health/live` | K8s Liveness Probe |
| GET | `/api/health/ready` | K8s Readiness Probe |
| GET | `/api/metrics` | Prometheus 业务指标 |

---

## 数据库设计

### E-R 关系

```
┌──────────┐       ┌──────────────┐       ┌───────────────┐
│  users   │       │   articles   │       │  audit_logs   │
├──────────┤       ├──────────────┤       ├───────────────┤
│ id       │──┐    │ id           │──┐    │ id            │
│ name     │  │    │ author_id    │──┤    │ article_id    │── articles.id
│ email    │  │    │ title        │  │    │ auditor_id    │── users.id
│ role     │  │    │ content      │  │    │ action        │
│ password │  │    │ status       │  │    │ from_level    │
│ is_active│  │    │ approval_lvl │  │    │ to_level      │
└──────────┘  │    │ sensitive_hit│  │    │ comment       │
              │    │ reject_reason│  │    │ snapshot      │
              ├───│ current_audit│  │    │ created_at    │
              │    │ submitted_at │  │    └───────────────┘
              │    │ approved_at  │  │
              │    └──────────────┘  │
              │                     │
              │    ┌───────────────────┐
              │    │ sensitive_words   │
              │    ├───────────────────┤
              │    │ word (UNIQUE)     │
              │    │ level             │
              │    │ is_active         │
              │    └───────────────────┘
              │              │
              │    ┌────────────────────┐
              │    │sensitive_word_hits │
              ├────│ article_id         │
              │    │ sensitive_word_id  │
              │    │ word               │
              │    │ position           │
              └────│ auditor_id         │
                   └────────────────────┘
```

### 稿件状态流转

| 状态 | 说明 |
|------|------|
| `draft` | 草稿，仅作者可见 |
| `pending` | 待审核 |
| `editor_approved` | 编辑已通过（等待上级审核） |
| `chief_approved` | 主管已通过（等待终审） |
| `published` | 已发布 |
| `rejected` | 已驳回 |

---

## 部署架构

```
                          Internet
                             │
                    ┌────────┴────────┐
                    │  Nginx :80      │
                    │  (限流 + Gzip   │
                    │   + 安全头)     │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
       ┌──────▼──────┐ ┌────▼─────┐ ┌──────▼──────┐
       │  PHP-FPM    │ │ Horizon  │ │  Scheduler  │
       │  (Web API)  │ │ (Queue)  │ │  (Cron)     │
       └──────┬──────┘ └────┬─────┘ └──────┬──────┘
              │             │              │
       ┌──────▼──────┐ ┌────▼─────┐ ┌──────▼──────┐
       │   MySQL 8   │ │  Redis 7 │ │   Reverb    │
       │   :3306     │ │  :6379   │ │  :8081      │
       └─────────────┘ └──────────┘ │ (WebSocket) │
                                    └─────────────┘

监控栈:
┌───────────┐ ┌─────────┐ ┌─────────────────────────┐
│Prometheus │ │ Grafana │ │ Node/Redis/MySQL          │
│  :9090    │ │  :3001  │ │     Exporter              │
└───────────┘ └─────────┘ └─────────────────────────┘
```

### 容器说明

| 容器 | 镜像 | 端口 | 说明 |
|------|------|------|------|
| `app` | 自构建 (php:8.2-fpm-alpine) | 80 | Nginx + PHP-FPM + Supervisor |
| `mysql` | mysql:8.0 | 3306 | 数据库（健康检查） |
| `redis` | redis:7-alpine | 6379 | 缓存/队列/广播驱动 |
| `horizon` | 自构建 | — | 队列处理器（Supervisor 管理） |
| `reverb` | 自构建 | 8081 | WebSocket 服务 |
| `scheduler` | 自构建 | — | 定时任务（每分钟） |
| `prometheus` | prom/prometheus | 9090 | 指标收集 |
| `grafana` | grafana/grafana | 3001 | 监控面板 |
| `*-exporter` | 官方 exporter | — | 基础设施指标采集 |

---

## 监控

### Prometheus 自定义指标 (`/api/metrics`)

| 指标 | 说明 |
|------|------|
| `audit_articles_total` | 各状态下稿件数量 |
| `sensitive_words_count` | 活跃敏感词总数 |
| `laravel_queue_size` | 队列待处理任务数 |
| `users_total` | 用户总数 |
| `php_info` | PHP 版本信息 |
| `app_info` | 应用版本 + 环境 |

### Grafana 面板

- 预配置仪表板（`docker/grafana/dashboards/`）
- 访问：`http://<host>:3001`（默认 admin/admin）

---

## CI/CD

使用 GitLab CI 实现三阶段流水线：

| 阶段 | 操作 | 触发方式 |
|------|------|----------|
| **test** | PHP 单元测试 + 覆盖率报告 | 任意分支推送 |
| **build** | 前端 Vite 构建 + Docker 镜像构建推送 | master 分支 |
| **deploy** | SSH 到生产服务器执行 `docker compose pull && up -d` | 手动触发 |

---

## 开发规范

- **后端**：Laravel 11 标准项目结构，控制器 → Service → Model 分层
- **前端**：Vue 3 Composition API + `<script setup>`，Pinia 状态管理，组件按功能拆分
- **API**：RESTful 风格，统一 JSON 响应格式，请求验证通过 FormRequest
- **队列**：敏感词检测等耗时操作异步执行，通过 Horizon 监控
- **广播**：审核状态变更通过 Reverb WebSocket 实时推送
- **安全**：CSP 头 + Nginx 限流 + Sanctum Token 认证 + CORS 限制

---

## License

MIT
