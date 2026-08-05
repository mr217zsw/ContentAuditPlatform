// ============================================================
// Content Audit Platform - K6 性能压测脚本
// 用法:
//   k6 run tests/k6/load-test.js
//   k6 run --vus 50 --duration 60s tests/k6/load-test.js
//   k6 run --vus 100 --duration 5m tests/k6/stress-test.js
// ============================================================

import { check, sleep, group } from 'k6';
import http from 'k6/http';
import { Rate, Trend, Counter } from 'k6/metrics';
import { htmlReport } from "https://raw.githubusercontent.com/benc-uk/k6-reporter/main/dist/bundle.js";

// ==================== 配置 (可通过环境变量覆盖) ====================
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';
const API_PREFIX = '/api/v1';

// ==================== 自定义指标 ====================
const errorRate = new Rate('errors');
const loginDuration = new Trend('login_duration', true);
const apiDuration = new Trend('api_duration', true);
const successCounter = new Counter('success_requests');

// ==================== 压测配置 ====================
export const options = {
  // 阶段式加压
  stages: [
    { duration: '30s', target: 10 },   // 预热: 30秒内逐步到 10 VUs
    { duration: '1m',  target: 50 },   // 加压: 1分钟内到 50 VUs
    { duration: '2m',  target: 100 },  // 满载: 2分钟内到 100 VUs
    { duration: '3m',  target: 100 },  // 稳定: 100 VUs 持续 3 分钟
    { duration: '1m',  target: 0 },    // 冷却: 1分钟逐步归零
  ],

  thresholds: {
    // 95% 请求必须在 2 秒内完成
    'http_req_duration': ['p(95)<2000'],
    // 错误率必须低于 1%
    'errors': ['rate<0.01'],
    // API 响应时间限制
    'api_duration': ['p(95)<1000'],
  },
};

// ==================== 认证令牌缓存 ====================
let authToken = null;

function login() {
  const payload = JSON.stringify({
    email: 'admin@example.com',
    password: 'password',
  });

  const res = http.post(`${BASE_URL}${API_PREFIX}/auth/login`, payload, {
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    tags: { name: 'login' },
  });

  loginDuration.add(res.timings.duration);

  const success = check(res, {
    'login status 200': (r) => r.status === 200,
    'login has token': (r) => r.json('token') !== undefined,
  });

  errorRate.add(!success);

  if (success) {
    authToken = res.json('token');
    successCounter.add(1);
  }
}

function authenticatedHeaders() {
  return {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'Authorization': `Bearer ${authToken}`,
  };
}

// ==================== 测试套件 ====================
export default function () {
  // 确保已登录
  if (!authToken) {
    login();
    if (!authToken) return;
  }

  group('Dashboard & Monitoring', () => {
    // 健康检查
    let res = http.get(`${BASE_URL}/api/health`, { tags: { name: 'health_check' } });
    check(res, { 'health OK': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);

    // Prometheus 指标
    res = http.get(`${BASE_URL}/api/metrics`, { tags: { name: 'metrics' } });
    check(res, { 'metrics OK': (r) => r.status === 200 });

    // 看板统计
    res = http.get(`${BASE_URL}${API_PREFIX}/dashboard/stats`, {
      headers: authenticatedHeaders(),
      tags: { name: 'dashboard_stats' },
    });
    apiDuration.add(res.timings.duration);
    check(res, { 'stats OK': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);
  });

  sleep(1);

  group('Articles CRUD', () => {
    // 稿件列表
    let res = http.get(`${BASE_URL}${API_PREFIX}/articles`, {
      headers: authenticatedHeaders(),
      tags: { name: 'articles_list' },
    });
    apiDuration.add(res.timings.duration);
    check(res, { 'list OK': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);

    sleep(0.5);

    // 创建稿件
    const articlePayload = JSON.stringify({
      title: `压测稿件 ${Date.now()}`,
      content: '这是一篇用于性能测试的稿件内容，包含足够多的文字以确保审核流程正常进行。',
      category: 'test',
    });

    res = http.post(`${BASE_URL}${API_PREFIX}/articles`, articlePayload, {
      headers: authenticatedHeaders(),
      tags: { name: 'articles_create' },
    });
    apiDuration.add(res.timings.duration);
    const created = check(res, { 'create OK': (r) => r.status === 201 || r.status === 200 });
    errorRate.add(!created);

    if (created) {
      successCounter.add(1);
      const articleId = res.json('data.id') || res.json('id');

      if (articleId) {
        sleep(0.5);

        // 查看稿件
        res = http.get(`${BASE_URL}${API_PREFIX}/articles/${articleId}`, {
          headers: authenticatedHeaders(),
          tags: { name: 'articles_show' },
        });
        apiDuration.add(res.timings.duration);
        check(res, { 'show OK': (r) => r.status === 200 });
        errorRate.add(res.status !== 200);

        sleep(0.5);

        // 提交审核
        res = http.post(`${BASE_URL}${API_PREFIX}/articles/${articleId}/submit`, '{}', {
          headers: authenticatedHeaders(),
          tags: { name: 'articles_submit' },
        });
        apiDuration.add(res.timings.duration);
        errorRate.add(res.status !== 200);
      }
    }
  });

  sleep(1);

  group('Audit Operations', () => {
    // 待审核列表
    let res = http.get(`${BASE_URL}${API_PREFIX}/audit/pending`, {
      headers: authenticatedHeaders(),
      tags: { name: 'audit_pending' },
    });
    apiDuration.add(res.timings.duration);
    check(res, { 'pending OK': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);

    sleep(0.5);

    // 审核历史
    res = http.get(`${BASE_URL}${API_PREFIX}/audit/history`, {
      headers: authenticatedHeaders(),
      tags: { name: 'audit_history' },
    });
    apiDuration.add(res.timings.duration);
    check(res, { 'history OK': (r) => r.status === 200 });
    errorRate.add(res.status !== 200);
  });

  sleep(2);
}

// ==================== 测试报告 (HTML) ====================
export function handleSummary(data) {
  // 控制台摘要
  console.log('');
  console.log('========================================');
  console.log('  K6 性能压测结果摘要');
  console.log('========================================');
  console.log(`  总请求数:     ${data.metrics.http_reqs?.values?.count || 0}`);
  console.log(`  失败请求数:   ${data.metrics.http_req_failed?.values?.passes || 0}`);
  console.log(`  平均响应:     ${(data.metrics.http_req_duration?.values?.avg || 0).toFixed(2)}ms`);
  console.log(`  P95 响应:     ${(data.metrics.http_req_duration?.values?.['p(95)'] || 0).toFixed(2)}ms`);
  console.log(`  P99 响应:     ${(data.metrics.http_req_duration?.values?.['p(99)'] || 0).toFixed(2)}ms`);
  console.log('========================================');

  return {
    'tests/k6/report.html': htmlReport(data),
    stdout: `
========================================
  K6 性能压测结果摘要
========================================
  总请求数:     ${data.metrics.http_reqs?.values?.count || 0}
  失败请求数:   ${data.metrics.http_req_failed?.values?.passes || 0}
  平均响应:     ${(data.metrics.http_req_duration?.values?.avg || 0).toFixed(2)}ms
  P95 响应:     ${(data.metrics.http_req_duration?.values?.['p(95)'] || 0).toFixed(2)}ms
  P99 响应:     ${(data.metrics.http_req_duration?.values?.['p(99)'] || 0).toFixed(2)}ms
========================================
`,
  };
}
