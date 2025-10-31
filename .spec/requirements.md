# 需求规范：claude-todo-bundle

## 概述

### 核心价值
claude-todo-bundle 是一个轻量级的任务管理和AI执行包，专为Symfony开发者设计。它解决了以下核心问题：
- 提供结构化的任务队列管理，特别适合需要AI辅助的开发任务
- 实现任务的持久化存储和生命周期管理
- 通过分组机制组织不同类型的任务
- 无缝集成Claude AI进行任务自动执行

### 目标用户
- **Symfony开发者**：需要在项目中集成AI辅助功能
- **DevOps团队**：自动化运维和部署任务
- **开发团队**：管理代码生成、文档生成、测试用例生成等AI任务

## 功能需求（EARS格式）

### 1. TODO任务管理

#### 基础功能
- **[R001]** Package必须提供TodoTask实体来存储任务信息
- **[R002]** TodoTask必须包含以下属性：ID、groupName、description、status、priority、createdTime、updatedTime、executedTime、result
- **[R003]** 当创建新任务时，Package必须自动设置createdTime时间戳和'pending'状态
- **[R004]** Package必须支持以下任务状态：pending（待处理）、in_progress（进行中）、completed（已完成）、failed（失败）

#### 任务操作
- **[R005]** Package必须提供TodoManagerInterface用于任务的CRUD操作
- **[R006]** 当调用push方法时，Package必须创建新任务并返回TodoTask实例
- **[R007]** 当调用pop方法时，Package必须返回最早的pending任务并将其状态更新为in_progress
- **[R008]** 如果指定了group参数，pop方法必须只返回该分组的任务
- **[R070]** 当调用pop方法时，Package必须过滤掉有任务处于in_progress状态的分组，确保同一分组内的任务串行执行
- **[R071]** 如果当前分组有任务正在执行，Package必须查找其他分组的pending任务
- **[R072]** 当没有可用任务时（所有分组都有任务在执行或没有pending任务），pop方法必须返回null
- **[R009]** 当任务完成时，Package必须记录executedTime时间戳和执行结果

### 2. 命令行接口

#### 2.1 claude-todo:push命令
- **[R010]** Package必须提供claude-todo:push命令用于添加新任务
- **[R011]** 命令必须接受两个必填参数：group-name（字符串）和description（字符串）
- **[R012]** 当参数缺失时，命令必须显示清晰的错误信息
- **[R013]** 成功创建任务后，命令必须显示任务ID和确认信息
- **[R014]** 如果任务创建失败，命令必须返回非零退出码

#### 2.2 claude-todo:pop命令
- **[R015]** Package必须提供claude-todo:pop命令用于获取待处理任务
- **[R016]** 命令必须支持可选的group-name参数用于过滤
- **[R017]** 当获取到任务时，命令必须显示任务ID、分组、描述和创建时间
- **[R018]** 如果没有待处理任务，命令必须进入等待循环，每隔3秒检查一次，直到有可用任务或用户中断
- **[R073]** 在等待循环中，命令必须显示等待状态和已等待时间
- **[R074]** 命令必须支持--no-wait选项，当没有可用任务时立即退出而不等待
- **[R075]** 命令必须支持--max-wait选项（秒），设置最大等待时间
- **[R019]** 命令必须支持--format选项（json/table）用于输出格式控制

#### 2.3 claude-todo:run命令
- **[R020]** Package必须提供claude-todo:run命令用于执行任务
- **[R021]** 命令必须接受task-id参数（整数）
- **[R022]** 当任务不存在时，命令必须显示错误信息并返回退出码1
- **[R023]** 当任务状态不是pending或in_progress时，命令必须拒绝执行
- **[R024]** 命令必须在执行前显示任务信息并请求确认（除非使用--no-interaction）
- **[R078]** 命令必须支持--model选项，允许覆盖默认的Claude模型
- **[R079]** 命令必须支持--max-attempts选项（默认: 10），控制使用限制重试次数
- **[R025]** 如果Claude CLI调用失败，命令必须保留任务状态并显示错误信息

### 3. 数据存储

- **[R026]** Package必须使用Doctrine ORM进行数据持久化
- **[R027]** Package必须提供TodoTaskRepository实现标准的数据访问
- **[R028]** Repository必须提供findByStatus方法用于状态过滤
- **[R029]** Repository必须提供findByGroup方法用于分组过滤
- **[R076]** Repository必须提供getGroupsWithInProgressTasks方法，返回有进行中任务的分组列表
- **[R077]** Repository必须提供findNextAvailableTask方法，返回可执行的任务（排除有进行中任务的分组）
- **[R030]** 当并发pop任务时，Package必须使用数据库锁防止重复获取
- **[R031]** Package必须支持软删除，保留历史任务记录

### 4. Claude AI集成

#### Claude CLI集成
- **[R032]** Package必须提供ClaudeExecutorInterface用于执行抽象
- **[R080]** Package必须使用Symfony Process组件调用Claude CLI
- **[R081]** 执行器必须传递以下参数给Claude CLI：
  - --dangerously-skip-permissions
  - --print
  - --output-format=stream-json
  - --model=[可配置]
  - --verbose
- **[R033]** Package必须支持通过配置设置模型和其他Claude CLI参数
- **[R082]** 当Claude CLI不可用时，Package必须抛出明确的异常
- **[R083]** Package必须解析Claude CLI的stream-json输出格式
- **[R084]** 当遇到"Claude AI usage limit reached"错误时，Package必须：
  - 解析等待时间戳
  - 显示倒计时
  - 添加1-5分钟的随机延迟
  - 自动重试（最多10次）
- **[R085]** 当遇到"Request not allowed"错误时，Package必须等彇60-75秒后重试
- **[R035]** Package必须支持通过claude-runner.stop文件停止执行
- **[R036]** 命令必须实时流式输出Claude的响应

#### 执行管理
- **[R037]** 当执行任务时，Package必须将任务描述作为prompt传递给Claude CLI
- **[R038]** Package必须支持自定义prompt模板
- **[R039]** 执行结果必须完整保存在任务的result字段中
- **[R086]** Package必须记录执行总时间，包括等待和重试时间
- **[R087]** 执行完成后，Package必须显示总执行时间（小时/分钟/秒格式）
- **[R040]** Claude CLI本身无超时限制，但Package必须提供可配置的终止机制

#### 错误处理
- **[R041]** 当Claude CLI返回非零退出码时，Package必须记录详细错误信息
- **[R088]** Package必须区分不同类型的错误：
  - 使用限制错误：自动等待并重试
  - 权限错误：短暂等待后重试
  - 其他错误：立即失败
- **[R043]** Package必须提供TaskExecutionFailedEvent事件用于错误监控
- **[R089]** 命令必须同时输出标准输出和标准错误流

## 非功能需求

### 1. 质量标准
- **[R044]** Package必须通过PHPStan Level 8检查，零错误
- **[R045]** Package必须达到90%以上的单元测试覆盖率
- **[R046]** Package必须遵循PSR-12编码标准
- **[R047]** 所有公共API必须包含完整的PHPDoc注释
- **[R048]** Package必须提供完整的使用文档和示例

### 2. 性能要求
- **[R049]** pop操作的响应时间必须小于100ms（1000个任务规模）
- **[R050]** Package必须支持至少10000个任务的存储而不降低性能
- **[R051]** 内存使用必须与任务数量呈线性关系，无内存泄漏
- **[R052]** 数据库查询必须使用适当的索引优化

### 3. 扩展性
- **[R053]** Package必须提供TaskProcessorInterface允许自定义任务处理逻辑
- **[R054]** Package必须支持通过事件系统扩展任务生命周期
- **[R055]** Package必须允许通过配置添加自定义任务状态
- **[R056]** 如果需要使用其他AI提供商，Package必须支持通过实现ExecutorInterface进行扩展

### 4. 兼容性
- **[R057]** Package必须支持PHP 8.1及以上版本
- **[R058]** Package必须兼容Symfony 6.4 LTS版本
- **[R059]** Package必须支持Doctrine ORM 2.15及以上版本
- **[R060]** 当升级主版本时，Package必须提供清晰的迁移指南

### 5. 安全性
- **[R061]** Package必须对所有用户输入进行验证和清理
- **[R062]** API密钥必须通过环境变量配置，不得硬编码
- **[R063]** Package必须防止SQL注入和XSS攻击
- **[R064]** 敏感信息（如API密钥）不得出现在日志中

## 集成需求

### Symfony集成
- **[R065]** Package必须提供标准的Symfony Bundle结构
- **[R066]** Package必须支持自动服务注册和配置
- **[R067]** Package必须兼容Symfony Flex自动配置
- **[R068]** 如果使用了Symfony Messenger，Package必须提供集成适配器

### 配置选项
- **[R069]** Package必须支持以下配置选项：
  - claude.model: 使用的模型（默认: claude-sonnet-4-20250514）
  - claude.cli_path: Claude CLI路径（默认: claude）
  - claude.extra_args: 额外的CLI参数
  - task.max_attempts: 使用限制重试次数（默认: 10）
  - task.default_priority: 默认优先级（默认: normal）
  - task.stop_file: 停止文件路径（默认: claude-runner.stop）

## 验收标准

1. **功能完整性**
   - 所有三个命令可以正常工作
   - 任务的完整生命周期管理
   - Claude AI集成正常运行

2. **代码质量**
   - PHPStan Level 8 通过
   - 90%+ 测试覆盖率
   - 所有测试通过

3. **文档完整性**
   - README包含安装和使用说明
   - 所有公共API有PHPDoc
   - 配置选项有详细说明

4. **可扩展性验证**
   - 提供至少一个扩展示例
   - 事件系统正常工作
   - 自定义处理器可以集成
