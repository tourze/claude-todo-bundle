# Claude TODO Bundle

[English](README.md) | [中文](README.zh-CN.md)

一个用于管理TODO任务并集成Claude AI执行的Symfony Bundle。

## 功能特性

- 📝 TODO任务管理（创建、存储、检索）
- 🏷️ 任务分组管理
- 🤖 Claude AI集成，自动执行任务
- 🖥️ 命令行接口操作

## 安装

```bash
composer require tourze/claude-todo-bundle
```

## 配置

在 `config/packages/claude_todo.yaml` 中配置：

```yaml
claude_todo:
    claude:
        api_key: '%env(CLAUDE_API_KEY)%'
        model: 'claude-3-sonnet'
        max_tokens: 4000
    task:
        default_timeout: 300
        max_retries: 3
```

## 使用方法

### 添加任务

```bash
bin/console claude-todo:push "backend" "实现用户认证API"
```

### 获取任务

```bash
# 从所有任务中获取
bin/console claude-todo:pop

# 从指定分组获取
bin/console claude-todo:pop "backend"
```

### 列出任务

```bash
# 列出待处理和进行中的任务（默认）
bin/console claude-todo:list

# 列出指定分组的任务
bin/console claude-todo:list "backend"

# 列出所有状态的任务
bin/console claude-todo:list --all

# 列出特定状态的任务
bin/console claude-todo:list --status=completed

# 列出多个状态的任务
bin/console claude-todo:list --status=pending --status=failed

# 限制显示数量
bin/console claude-todo:list --limit=20
```

### 执行任务

```bash
bin/console claude-todo:run 123
```

### 清理任务

```bash
# 清理所有任务
bin/console claude-todo:clear --force

# 清理指定分组的任务
bin/console claude-todo:clear "backend" --force

# 交互式确认清理
bin/console claude-todo:clear
```

### 修复已完成任务时间

```bash
# 修复缺失完成时间的已完成任务
bin/console claude-todo:fix-completed-time

# 预览模式（不实际修改）
bin/console claude-todo:fix-completed-time --dry-run
```

### Worker模式（持续执行）

启动Worker持续监听并执行任务：

```bash
# 默认设置（永不超时）
bin/console claude-todo:worker

# 指定组
bin/console claude-todo:worker --group=backend

# 设置空闲超时（秒）
bin/console claude-todo:worker --idle-timeout=600

# 设置1小时后空闲退出
bin/console claude-todo:worker --idle-timeout=3600

# 自定义检查间隔和重试次数
bin/console claude-todo:worker --check-interval=5 --max-attempts=5
```

Worker会自动：
- 获取优先级最高的待处理任务
- 使用Claude CLI执行任务
- 更新任务状态（完成或失败）
- 继续处理下一个任务
- 当达到空闲超时时停止

## 开发

### 运行测试

```bash
composer test
```

### 代码检查

```bash
composer phpstan
composer cs-fix
```

## 许可证

MIT
