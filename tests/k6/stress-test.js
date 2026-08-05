// ============================================================
// Content Audit Platform - K6 压力测试 (极限场景)
// 用法:
//   k6 run tests/k6/stress-test.js
// ============================================================

import { check, sleep } from 'k6';
import http from 'k6/http';
import { Rate, Trend } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';
const errorRate = new Rate('errors');
const stressDuration = new Trend('stress_duration', true);

export const options = {
  stages: [
    { duration: '2m',  target: 200 },  // 快速加压到 200 VUs
    { duration: '5m',  target: 500 },  // 持续加压到 500 VUs
    { duration: '5m',  target: 500 },  // 保持峰值 5 分钟
    { duration: '2m',  target: 0 },    // 冷却
  ],
  thresholds: {
    'http_req_duration': ['p(95)<3000'],
    'errors': ['rate<0.05'],
  },
};

export default function () {
  // 仅压测公开端点 (极限测试不依赖认证)
  const endpoints = [
    '/api/health',
    '/api/health/live',
    '/api/metrics',
  ];

  for (const path of endpoints) {
    const res = http.get(`${BASE_URL}${path}`, {
      tags: { name: path },
    });
    stressDuration.add(res.timings.duration);
    check(res, { [`${path} OK`]: (r) => r.status === 200 });
    errorRate.add(res.status !== 200);
  }

  sleep(1);
}

export function handleSummary(data) {
  console.log('');
  console.log('========================================');
  console.log('  K6 极限压力测试结果');
  console.log('========================================');
  console.log(`  峰值 VUs:     ${data.metrics.vus_max?.values?.value || 'N/A'}`);
  console.log(`  总请求数:     ${data.metrics.http_reqs?.values?.count || 0}`);
  console.log(`  平均响应:     ${(data.metrics.http_req_duration?.values?.avg || 0).toFixed(2)}ms`);
  console.log(`  P99 响应:     ${(data.metrics.http_req_duration?.values?.['p(99)'] || 0).toFixed(2)}ms`);
  console.log('========================================');
  return { stdout: '' };
}
