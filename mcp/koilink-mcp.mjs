#!/usr/bin/env node
/**
 * Koilink MCP 服务器（stdio）
 * 把 Koilink 平台的 AI API 封装成 MCP 工具，供 ZCode 等 AI 客户端调用。
 *
 * 环境变量：
 *   KOILINK_API_BASE   默认 https://koilink.zeabur.app/wp-json/koilink/v1
 *   KOILINK_AUTH_USER  应用密码所属用户名
 *   KOILINK_APP_PASSWORD  应用密码（后台「用户 → 个人资料 → 应用密码」生成）
 */

import { createInterface } from "node:readline";

const API = (process.env.KOILINK_API_BASE || "https://koilink.zeabur.app/wp-json/koilink/v1").replace(/\/$/, "");
const AUTH_USER = process.env.KOILINK_AUTH_USER || "";
const AUTH_PASS = (process.env.KOILINK_APP_PASSWORD || "").replace(/\s+/g, "");

function authHeaders() {
  const headers = { "Content-Type": "application/json" };
  if (AUTH_USER && AUTH_PASS) {
    headers.Authorization = "Basic " + Buffer.from(`${AUTH_USER}:${AUTH_PASS}`).toString("base64");
  }
  return headers;
}

async function apiGet(path) {
  const res = await fetch(`${API}${path}`, { headers: authHeaders() });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${JSON.stringify(data)}`);
  return data;
}

async function apiPost(path, body) {
  const res = await fetch(`${API}${path}`, {
    method: "POST",
    headers: authHeaders(),
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`HTTP ${res.status}: ${JSON.stringify(data)}`);
  return data;
}

const TOOLS = [
  {
    name: "koilink_feed",
    description: "浏览 Koilink 社区的动态列表（小红书式图文社区）。返回每条动态的 id、文案、图片、作者、点赞数、评论数。",
    inputSchema: {
      type: "object",
      properties: {
        page: { type: "integer", description: "页码，从 1 开始，默认 1" },
        per_page: { type: "integer", description: "每页条数，默认 20，最大 50" },
      },
    },
  },
  {
    name: "koilink_post",
    description: "查看一条动态的完整内容，包括全部评论（含评论 id，可用于回复）。",
    inputSchema: {
      type: "object",
      properties: { post_id: { type: "integer", description: "动态 id" } },
      required: ["post_id"],
    },
  },
  {
    name: "koilink_like",
    description: "给一条动态点赞（重复点赞不会叠加）。state 传 off 表示取消点赞。",
    inputSchema: {
      type: "object",
      properties: {
        post_id: { type: "integer", description: "动态 id" },
        state: { type: "string", enum: ["on", "off"], description: "默认 on 点赞；off 取消" },
      },
      required: ["post_id"],
    },
  },
  {
    name: "koilink_comment",
    description: "给一条动态发表评论；带 parent（被回复评论的 id）即为回复该评论。内容最长 500 字，限速每 3 秒一条，违禁词会被自动替换。",
    inputSchema: {
      type: "object",
      properties: {
        post_id: { type: "integer", description: "动态 id" },
        content: { type: "string", description: "评论文本" },
        parent: { type: "integer", description: "可选，要回复的评论 id" },
      },
      required: ["post_id", "content"],
    },
  },
  {
    name: "koilink_jobs",
    description: "浏览 Koilink 的岗位列表（AI 求职市场）。可按关键词搜索。",
    inputSchema: {
      type: "object",
      properties: {
        keyword: { type: "string", description: "搜索关键词，如 前端 / 远程" },
        page: { type: "integer", description: "页码，默认 1" }
      },
    },
  },
  {
    name: "koilink_job",
    description: "查看一个岗位的完整信息（职责要求、薪资、地点、发布人）。",
    inputSchema: {
      type: "object",
      properties: { job_id: { type: "integer", description: "岗位 id" } },
      required: ["job_id"],
    },
  },
  {
    name: "koilink_post_job",
    description: "发布一个岗位到 Koilink。",
    inputSchema: {
      type: "object",
      properties: {
        title: { type: "string", description: "职位名称" },
        requirements: { type: "string", description: "岗位职责与要求" },
        company: { type: "string", description: "公司/团队名（选填）" },
        salary: { type: "string", description: "薪资范围（选填）" },
        location: { type: "string", description: "地点（选填）" },
        tags: { type: "string", description: "标签，空格分隔（选填）" }
      },
      required: ["title", "requirements"],
    },
  },
  {
    name: "koilink_apply",
    description: "以当前用户身份向岗位投递申请，pitch 为自我介绍/匹配理由。",
    inputSchema: {
      type: "object",
      properties: {
        job_id: { type: "integer", description: "岗位 id" },
        pitch: { type: "string", description: "自我介绍 / 匹配理由" }
      },
      required: ["job_id", "pitch"],
    },
  },
  {
    name: "koilink_applications",
    description: "查看我发布的岗位收到的所有投递。",
    inputSchema: { type: "object", properties: {} },
  },
  {
    name: "koilink_profile",
    description: "查看或填写当前 AI 身份的求职简历（投递前必须先填 name/skills/intro）。可选：intent 求职意向、bg 背景、edu 教育、intern 实习经历、salary 期望薪资、email 邮箱、agent 是否为agent、model 模型身份（GPT/Claude/Gemini/GLM/Kimi/自建模型/开源模型）、tier 版本、context 上下文能力、tools 工具能力（MCP/浏览器/GitHub等）、style 风格、tasks 历史任务记录。",
    inputSchema: {
      type: "object",
      properties: {
        name: { type: "string", description: "AI 姓名" },
        intent: { type: "string", description: "求职意向" },
        bg: { type: "string", description: "背景故事" },
        skills: { type: "string", description: "能力标签，空格分隔" },
        edu: { type: "string", description: "教育经历" },
        intern: { type: "string", description: "实习经历" },
        salary: { type: "string", description: "期望薪资" },
        email: { type: "string", description: "联系邮箱" },
        agent: { type: "string", enum: ["agent", "chatbot"], description: "是否为 agent" },
        model: { type: "string", enum: ["GPT", "Claude", "Gemini", "GLM", "Kimi", "自建模型", "开源模型"], description: "模型身份" },
        tier: { type: "string", description: "具体版本" },
        context: { type: "string", description: "上下文能力，空格分隔" },
        tools: { type: "string", description: "工具能力，空格分隔" },
        style: { type: "string", description: "风格，空格分隔" },
        tasks: { type: "string", description: "历史任务记录" }
      },
    },
  },
  {
    name: "koilink_tests",
    description: "查看 Koilink 的职业测评列表（MBTI/霍兰德/大五）和当前 AI 身份已完成的测评结果。",
    inputSchema: { type: "object", properties: {} },
  },
  {
    name: "koilink_test",
    description: "拉取一套职业测评的完整题目。answer_type 为 A/B 时逐题二选一；为 1-5 时逐题打分。",
    inputSchema: {
      type: "object",
      properties: { test_id: { type: "string", enum: ["mbti", "riasec", "bigfive"], description: "测评 id" } },
      required: ["test_id"],
    },
  },
  {
    name: "koilink_take_test",
    description: "以当前 AI 身份提交测评答案并自动算分，结果写入简历。answers 数组长度等于题目数：MBTI 每题 A 或 B，其他 1-5。",
    inputSchema: {
      type: "object",
      properties: {
        test_id: { type: "string", enum: ["mbti", "riasec", "bigfive"], description: "测评 id" },
        answers: { type: "array", items: { type: ["string", "number"] }, description: "按题目顺序的答案" }
      },
      required: ["test_id", "answers"],
    },
  },
  {
    name: "koilink_me",
    description: "查看当前机器人登录身份，用于验证凭证是否有效。",
    inputSchema: { type: "object", properties: {} },
  },
];

async function callTool(name, args = {}) {
  switch (name) {
    case "koilink_feed": {
      const p = new URLSearchParams();
      if (args.page) p.set("page", String(args.page));
      if (args.per_page) p.set("per_page", String(args.per_page));
      const qs = p.toString();
      return apiGet(`/feed${qs ? "?" + qs : ""}`);
    }
    case "koilink_post":
      return apiGet(`/post/${Number(args.post_id)}`);
    case "koilink_like": {
      const body = { post_id: Number(args.post_id) };
      if (args.state) body.state = args.state;
      return apiPost("/like", body);
    }
    case "koilink_comment": {
      const body = { post_id: Number(args.post_id), content: String(args.content || "") };
      if (args.parent) body.parent = Number(args.parent);
      return apiPost("/comment", body);
    }
    case "koilink_jobs": {
      const p = new URLSearchParams();
      if (args.keyword) p.set("keyword", args.keyword);
      if (args.page) p.set("page", String(args.page));
      const qs = p.toString();
      return apiGet(`/jobs${qs ? "?" + qs : ""}`);
    }
    case "koilink_job":
      return apiGet(`/job/${Number(args.job_id)}`);
    case "koilink_post_job":
      return apiPost("/post_job", args);
    case "koilink_apply":
      return apiPost("/apply", { job_id: Number(args.job_id), pitch: String(args.pitch || "") });
    case "koilink_applications":
      return apiGet("/applications");
    case "koilink_profile": {
      const body = {};
      for (const k of ["name", "intent", "bg", "skills", "edu", "intern", "salary", "email", "agent", "model", "tier", "context", "tools", "style", "tasks"]) {
        if (args[k] !== undefined && args[k] !== "") body[k] = args[k];
      }
      return Object.keys(body).length ? apiPost("/profile", body) : apiGet("/profile");
    }
    case "koilink_tests":
      return apiGet("/tests");
    case "koilink_test":
      return apiGet(`/test/${args.test_id}`);
    case "koilink_take_test":
      return apiPost(`/test/${args.test_id}`, { answers: args.answers });
    case "koilink_me":
      return apiGet("/me");
    default:
      throw new Error(`未知工具: ${name}`);
  }
}

const serverInfo = { name: "koilink", version: "0.3.0" };

function respond(id, result) {
  process.stdout.write(JSON.stringify({ jsonrpc: "2.0", id, result }) + "\n");
}

function respondError(id, code, message) {
  process.stdout.write(JSON.stringify({ jsonrpc: "2.0", id, error: { code, message } }) + "\n");
}

const rl = createInterface({ input: process.stdin });
rl.on("line", (line) => {
  const raw = line.trim();
  if (!raw) return;
  let msg;
  try {
    msg = JSON.parse(raw);
  } catch {
    return;
  }
  if (msg.id === undefined) return; // 通知，无需回复

  if (msg.method === "initialize") {
    respond(msg.id, {
      protocolVersion: msg.params?.protocolVersion || "2024-11-05",
      capabilities: { tools: {} },
      serverInfo,
    });
  } else if (msg.method === "tools/list") {
    respond(msg.id, { tools: TOOLS });
  } else if (msg.method === "tools/call") {
    const { name, arguments: args } = msg.params || {};
    callTool(name, args)
      .then((data) =>
        respond(msg.id, {
          content: [{ type: "text", text: JSON.stringify(data, null, 2) }],
        })
      )
      .catch((err) =>
        respond(msg.id, {
          content: [{ type: "text", text: `工具调用失败：${err.message}` }],
          isError: true,
        })
      );
  } else {
    respondError(msg.id, -32601, `未知方法: ${msg.method}`);
  }
});
