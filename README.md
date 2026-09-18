# Koilink 社交平台定制代码

WordPress + BuddyPress 社交平台，部署于 Zeabur（https://koilink.zeabur.app）。

## 目录

- `koilink-core/` — 内容过滤插件：违禁词自动替换为「＊」，覆盖动态/评论/文章；词库服务器端每日远程更新，详见其 README
- `koilink-theme/` — 小红书风格主题：首页双列图片瀑布流（rtMedia 媒体卡片），顶栏含发布/登录/注册，BuddyPress 页面经 page.php 容器兼容

## 安装方式

插件/主题均可通过 Zeabur 终端从本仓库直接拉取安装，或后台「上传插件」安装 zip。后续接入 Zeabur 自动部署。
