# Loco Translate (Modified by NieRMHY)

这是基于 [Loco Translate](https://wordpress.org/plugins/loco-translate/) 的修改版本。

## 原始项目
- 原作者: Tim Whitlock
- 原始项目: https://wordpress.org/plugins/loco-translate/
- Official [Loco](https://localise.biz/)
- [plugin page](https://localise.biz/wordpress/plugin).
- 许可证: GPLv2 or later
- readme.txt

## 修改说明
- 新增 `Loco_api_OpenAiCompatible` 基类，统一处理所有兼容 OpenAI Chat Completions 协议的翻译服务。
- 扩展 `ChatGpt`、`DeepSeek` 以及 `OpenAiGeneric` 三个客户端，支持 OpenAI 官方、DeepSeek 以及任意兼容端点的批量翻译。
- 管理后台新增 DeepSeek 与“OpenAI Compatible” 两套 API 配置项，可自定义模型、提示词、端点与 max_tokens 等参数。
- 翻译 Ajax 控制器增加相应分支，确保前端批量翻译入口可直接调用新接入的服务。

## 许可证
本项目采用 GPLv3 许可证，详见 LICENSE 文件。