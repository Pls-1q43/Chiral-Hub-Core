# Chiral Hub Core 插件开发文档

## 1. 项目概述

Chiral Hub Core (`chiral-hub-core`) 作为 Chiral Network 的中心枢纽，负责管理来自各个 Chiral Connector 节点的数据同步、用户角色权限控制、REST API 接口提供以及与 Jetpack 等外部服务的集成。其核心目标是构建一个统一的数据中心，聚合来自不同源站点的内容，并通过 Jetpack 的相关文章功能增强内容的关联性。

本文档旨在详细阐述 Chiral Hub Core 插件的各项功能、核心组件、实现机制以及关键业务逻辑，为后续的开发、维护和功能扩展提供参考。

## 2. 核心组件与功能

### 2.1. 插件初始化与核心类 (`chiral-hub-core.php`, `class-chiral-hub-core.php`)

- **`chiral-hub-core.php`**: 插件主入口文件，定义了插件的基本信息（版本、名称、路径常量等），引入核心类 `Chiral_Hub_Core`，并注册了插件激活 (`chiral_hub_core_activate`) 和停用 (`chiral_hub_core_deactivate`) 的钩子函数。
- **`Chiral_Hub_Core`**: 插件的核心类，负责整体的协调和管理。主要职责包括：
    - **加载依赖**: 初始化并加载各个功能模块的类，如加载器 (`Chiral_Hub_Core_Loader`)、国际化 (`Chiral_Hub_Core_I18n`)、自定义文章类型 (`Chiral_Hub_CPT`)、用户角色 (`Chiral_Hub_Roles`)、REST API (`Chiral_Hub_Rest_Api`)、数据同步 (`Chiral_Hub_Sync`)、重定向 (`Chiral_Hub_Redirect`)、Jetpack 集成 (`Chiral_Hub_Jetpack`)、节点检查器 (`Chiral_Hub_Node_Checker`) 以及管理员和 Porter 用户的管理界面等。
    - **设置区域语言**: 调用 `Chiral_Hub_Core_I18n` 类加载插件的文本域，以支持国际化。
    - **定义钩子**: 通过 `Chiral_Hub_Core_Loader` 实例，注册各个模块中定义的 WordPress action 和 filter 钩子，将特定的功能挂载到 WordPress 的执行流程中。
    - **运行插件**: 调用 `Chiral_Hub_Core_Loader` 的 `run` 方法，执行所有已注册的钩子。

### 2.2. 加载器 (`class-chiral-hub-core-loader.php`)

`Chiral_Hub_Core_Loader` 类是一个通用的钩子管理工具。它维护一个 action 和 filter 的集合，并提供了 `add_action` 和 `add_filter` 方法来注册钩子，以及一个 `run` 方法来遍历并执行所有已注册的钩子。这使得插件的钩子管理更加集中和有序。

### 2.3. 激活与停用 (`class-chiral-hub-activator.php`, `class-chiral-hub-deactivator.php`)

- **`Chiral_Hub_Activator`**: 插件激活时执行的逻辑：
    - **添加 Chiral Porter 角色**: 调用 `Chiral_Hub_Roles::add_role()` 创建 `chiral_porter` 用户角色并赋予其特定权限。
    - **注册 CPT**: 调用 `Chiral_Hub_CPT::register_cpt()` 注册 `chiral_data` 自定义文章类型。
    - **设置默认选项**: 初始化插件的默认设置，如网络名称、新 Porter 注册策略、默认相关文章数量、Hub 传输模式等，并存储在 `chiral-hub-core_options` 选项中。
    - **调度节点状态检查**: 调用 `Chiral_Hub_Node_Checker::schedule_daily_checks()` 设置每日检查连接节点状态的定时任务 (`chiral_hub_daily_node_check`)。
    - **刷新重写规则**: 调用 `flush_rewrite_rules()` 以确保 CPT 的 URL 结构生效。
- **`Chiral_Hub_Deactivator`**: 插件停用时执行的逻辑：
    - **取消定时任务**: 调用 `Chiral_Hub_Node_Checker::unschedule_daily_checks()` 清除每日节点状态检查的定时任务。
    - **刷新重写规则**: 再次调用 `flush_rewrite_rules()`。
    - **注意**: 停用时并未移除 Chiral Porter 角色或 `chiral_data` CPT，也未删除插件选项。这些操作通常在插件卸载时处理。

### 2.4. 国际化 (`class-chiral-hub-i18n.php`)

`Chiral_Hub_Core_I18n` 类负责加载插件的翻译文件。它通过 `load_plugin_textdomain` 函数，根据 WordPress 的语言设置加载位于 `languages` 目录下的 `.mo` 文件，使得插件界面和文本可以被翻译成不同语言。

### 2.5. 自定义文章类型 (CPT) (`class-chiral-hub-cpt.php`)

`Chiral_Hub_CPT` 类定义了 `chiral_data` 自定义文章类型，用于存储从各个 Chiral Connector 节点同步过来的数据。
- **CPT 注册**: `register_cpt` 方法使用 `register_post_type` 函数注册 `chiral_data` CPT，定义了其标签、参数（如 `public`, `show_ui`, `supports`, `rewrite`, `capability_type` 等）。特别地，`supports` 参数中包含了 `title`, `editor`, `excerpt`, `author`, `thumbnail`, `custom-fields` 以及 `'jetpack-related-posts'`，后者是为了确保 Jetpack 相关文章功能可以作用于此 CPT。
- **Meta 字段**: 定义了多个与 `chiral_data` 相关的 meta key 常量，如 `chiral_source_url`, `_chiral_node_id`, `_chiral_data_original_post_id` 等，用于存储源文章的各种信息。
- **Porter 用户菜单定制**: `customize_chiral_porter_admin_menu` 方法会移除 Chiral Porter 用户在后台看到的 `chiral_data` CPT 的标准菜单，引导他们使用插件提供的专用管理界面。
- **REST API 响应注入网络名称**: `inject_network_name_to_rest_response` 方法通过 `register_rest_field` 为 `chiral_data` CPT 的 REST API 响应添加了一个 `chiral_network_name` 字段，其值从插件设置或文章的 meta 数据中获取。

### 2.6. 用户角色与权限 (`class-chiral-hub-roles.php`)

`Chiral_Hub_Roles` 类负责管理 `chiral_porter` 用户角色及其权限。
- **角色定义**: `ROLE_SLUG` 常量定义为 `chiral_porter`，`ROLE_NAME` 定义为 `Chiral Porter`。
- **添加/移除角色**: `add_role` 和 `remove_role` 方法分别用于在插件激活和卸载（理论上）时添加和移除此角色。
- **权限设置**: `chiral_porter` 角色被赋予了一系列针对 `chiral_data` CPT 的操作权限，如 `edit_chiral_data`, `read_chiral_data`, `delete_chiral_data`, `edit_published_chiral_datas`, `publish_chiral_datas`, `delete_published_chiral_datas` 等，以及 `upload_files` 权限。这些权限通过 `map_meta_cap` 钩子进行细致映射。
- **仪表盘访问限制**: `restrict_dashboard_access` 方法会阻止 `chiral_porter` 用户访问标准的 WordPress 仪表盘，并将他们重定向到个人资料页面或插件提供的专用管理界面。
- **CPT 权限映射**: `map_chiral_data_meta_caps` 方法通过 `map_meta_cap` filter 钩子，将通用的 `edit_post`, `read_post`, `delete_post` 等权限映射到针对 `chiral_data` CPT 的特定权限，确保 `chiral_porter` 用户只能操作他们自己的或其所属节点的数据。

### 2.7. REST API (`class-chiral-hub-rest-api.php`)

`Chiral_Hub_Rest_Api` 类定义了插件的自定义 REST API 端点。
- **注册端点**: `register_routes` 方法通过 `register_rest_route` 函数注册了以下端点：
    - **`/chiral-hub/v1/related-data` (GET)**: 用于获取指定 `chiral_data` 文章的相关文章。它接收 `post_id` 和可选的 `count` 参数。内部调用 `Chiral_Hub_Jetpack::get_related_posts_for_id` 方法获取 Jetpack 提供的相关文章数据，并进行格式化处理，包含文章 ID、标题、链接、摘要、特色图片 URL 和来源网络名称。
        - **权限回调**: `get_related_data_permission_check` 确保只有拥有 `read` 权限的用户（通常是所有用户）可以访问此端点。
    - **`/chiral-hub/v1/ping` (GET)**: 一个简单的 ping 端点，用于测试 Hub 是否可达。返回 Hub 的网络名称和插件版本。
        - **权限回调**: `ping_permission_check` 确保只有认证的 `chiral_porter` 用户可以访问此端点。
- **控制 `chiral_source_url` 可见性**: `control_source_url_visibility_in_rest` 方法通过 `rest_prepare_chiral_data` filter 钩子，根据插件设置中的 `enable_hub_transfer_mode` 选项，动态决定是否在 `chiral_data` CPT 的标准 REST API 响应中包含 `chiral_source_url` 字段。如果 Hub 传输模式启用，则不包含此字段，以强制流量通过 Hub 进行重定向。

### 2.8. 数据同步 (`class-chiral-hub-sync.php`)

`Chiral_Hub_Sync` 类是处理从 Chiral Connector 节点传入数据的核心。它主要通过拦截和修改 WordPress REST API 对 `chiral_data` CPT 的操作来实现。
- **Porter 注册策略**: 
    - `get_porter_registration_policy()`: 从插件设置中获取新 Porter 提交内容的处理策略（直接发布或设为待审）。
    - `get_enforced_status()`: 根据策略返回应强制执行的文章状态 (`publish` 或 `pending`)。
- **请求拦截与状态强制**: 
    - `intercept_chiral_data_requests()`: 通过 `rest_pre_dispatch` 钩子拦截 `/wp/v2/chiral_data` 的 `POST` 和 `DELETE` 请求。
        - 对于 `POST` (创建/更新) 请求，如果操作者是 `chiral_porter` 用户，则会强制将请求中的 `status` 参数修改为由 Porter 注册策略决定的状态，防止 Porter 自行设定文章状态。
        - 对于 `DELETE` 请求，如果操作者是 `chiral_porter` 且有权删除该文章，则执行永久删除 (`wp_delete_post` 第二个参数为 `true`)。
    - `verify_post_status_compliance()`: 通过 `rest_after_insert_chiral_data` 钩子，在文章创建或更新后再次验证其状态是否符合 Porter 注册策略，如果不符则强制更新。
    - `force_post_status_at_core_level()`: 通过 `wp_insert_post_data` 钩子，在 WordPress 核心层面最终强制设定文章状态，作为最高安全保障。
- **数据处理与存储**: 
    - `process_incoming_data()`: (此方法在当前代码中似乎未被直接调用，其逻辑可能已分散到 REST API 钩子中) 设计用于处理来自节点的完整数据包，包括数据校验、查找已存在文章、准备文章参数、插入或更新文章、设置特色图片等。
    - `sanitize_data()`: 对传入的数据进行清理和格式化。
    - `find_existing_chiral_data()`: 根据 `source_url`, `node_id`, `original_post_id` 查找 Hub 中是否已存在对应的 `chiral_data` 文章。
    - `set_featured_image_from_url()`: 从给定的 URL 下载图片并设置为文章的特色图片。
- **REST API 插入前/后处理**: 
    - `handle_rest_pre_insert_chiral_data()`: 通过 `rest_pre_insert_chiral_data` (对于 CPT 是 `rest_pre_insert_{$post_type}`) 钩子，在文章通过 REST API 插入前进行处理：
        - 生成唯一的 `post_name` (slug)。
        - 再次强制应用 Porter 注册策略决定的文章状态。
        - 设置 `post_author` 为当前 Porter 用户 ID。
        - 如果 Porter 用户的 `_chiral_node_id` meta 为空或与传入的不同，则更新它。
        - 确保 `other_URLs` meta 字段被正确设置。
        - 格式化 `_chiral_data_original_publish_date` meta 字段为 ISO 8601 格式。
    - `handle_rest_after_insert_chiral_data()`: 通过 `rest_after_insert_chiral_data` (对于 CPT 是 `rest_after_insert_{$post_type}`) 钩子，在文章通过 REST API 插入/更新后进行处理：
        - 调用 `ensure_network_name_metadata()` 确保文章拥有正确的 `chiral_network_name` meta。
        - 根据传入的 `_chiral_data_original_featured_image_url` meta 设置特色图片。
- **网络名称元数据**: 
    - `ensure_network_name_metadata()`: 确保指定的 `chiral_data` 文章拥有 `chiral_network_name` meta 字段，其值来自插件的全局设置。
    - `auto_set_network_name_on_save()`: 通过 `save_post_chiral_data` 钩子，在管理员手动保存 `chiral_data` 文章时，自动更新其 `chiral_network_name` meta。

### 2.9. 内容重定向 (`class-chiral-hub-redirect.php`)

`Chiral_Hub_Redirect` 类负责将访问 Hub 上 `chiral_data` 文章的请求重定向到其原始来源 URL。
- **初始化**: `init` 方法注册了两个钩子：`parse_request` 和 `template_redirect`。
- **早期重定向检查**: `early_redirect_check` 方法挂载在 `parse_request` 钩子上，在 WordPress 解析请求的早期阶段检查当前请求是否针对单个 `chiral_data` 文章。如果是，则获取文章 ID 并调用 `perform_redirect_if_needed`。
- **模板重定向检查**: `maybe_redirect_chiral_data_post` 方法挂载在 `template_redirect` 钩子上，作为备用检查。如果当前页面是单个 `chiral_data` 文章，则获取文章 ID 并调用 `perform_redirect_if_needed`。
- **执行重定向**: `perform_redirect_if_needed` 方法是核心重定向逻辑。它根据传入的文章 ID 获取 `chiral_source_url` meta 字段。如果该 URL 有效，则执行 301永久重定向到该 URL。**此重定向逻辑始终执行，不受 Hub 传输模式设置的影响。** (注意：这与 `Chiral_Hub_Rest_Api` 中根据传输模式控制 `chiral_source_url` 在 REST API 中可见性的逻辑有所不同，前端直接访问 Hub 上的 `chiral_data` 文章链接总是会被重定向)。

### 2.10. Jetpack 集成 (`class-chiral-hub-jetpack.php`)

`Chiral_Hub_Jetpack` 类负责与 Jetpack 插件，特别是其相关文章功能进行集成。
- **状态检查**: `is_jetpack_related_posts_active` 检查 Jetpack 是否已激活且其相关文章模块已启用，并且站点已连接到 WordPress.com。
- **获取相关文章**: `get_related_posts_for_id` 方法是核心功能，它调用 `Jetpack_RelatedPosts::init()->get_for_post_id()` 来获取指定 `chiral_data` 文章的相关文章。它会使用插件设置中的 `chiral_hub_core_default_related_count` 作为默认的相关文章数量。
- **CPT 支持与过滤器**: 
    - `add_cpt_jetpack_support()`: (静态方法) 用于确保 `chiral_data` CPT 的注册参数中包含 `jetpack-related-posts` 支持。理想情况下，这应在 CPT 注册时完成，此处作为后备。
    - `filter_rest_api_allowed_post_types()` 和 `emergency_rest_api_filter()`: 通过 `rest_api_allowed_post_types` 钩子，确保 `chiral_data` CPT 被添加到 Jetpack Sync（以及其他 REST API 消费者）允许访问的文章类型列表中。这是 Jetpack 相关文章功能能够处理 CPT 的关键。
    - `filter_jetpack_related_post_types()` 和 `emergency_jetpack_filter()`: 通过 `jetpack_relatedposts_filter_post_type` 钩子，确保 Jetpack 相关文章功能在查找相关内容时会考虑 `chiral_data` CPT。
    - `enable_related_posts_for_cpt()`: 通过 `jetpack_relatedposts_filter_enabled_for_request` 钩子，确保对 `chiral_data` CPT 的请求启用相关文章功能。
    - `force_register_filters()`: 在 `init` 钩子上以较高优先级（999）注册上述过滤器，确保它们被正确应用。
- **Jetpack Sync 元数据配置**: 
    - `configure_jetpack_sync_metadata()`: 通过 `jetpack_sync_post_meta_whitelist` 钩子，将 `chiral_data` CPT 使用的多个重要 meta key (如 `chiral_source_url`, `_chiral_node_id`, `chiral_network_name` 等) 添加到 Jetpack Sync 的白名单中，确保这些元数据能被同步到 WordPress.com，从而被相关文章算法使用。
    - `provide_dynamic_metadata_for_jetpack_sync()`: 通过 `jetpack_sync_post_meta_chiral_data` (动态钩子 `jetpack_sync_post_meta_{$post->post_type}`) 钩子，在 Jetpack 同步 `chiral_data` 文章的元数据时，如果 `chiral_network_name` meta 为空，则动态注入插件设置中的全局网络名称。这确保了即使文章本身没有这个 meta，Jetpack 也能获取到网络名称信息。
- **调试**: `debug_jetpack_configuration` 方法（仅在 `WP_DEBUG` 为 true 时运行）用于输出 Jetpack 配置相关的调试信息到错误日志。

### 2.11. 节点状态检查器 (`class-chiral-hub-node-checker.php`)

`Chiral_Hub_Node_Checker` 类负责检查已连接的 Chiral Connector 节点的状态。
- **检查单个节点**: `check_node_status` 方法接收 Porter 用户 ID，获取其关联的 `_chiral_node_id` 和 `_chiral_node_url` (如果 URL 未设置，则尝试从该 Porter 最近同步的文章的 `chiral_source_url` 中提取)。然后向节点的 `/wp-json/chiral-connector/v1/node-status` API 端点发送 GET 请求，获取节点的插件版本、相关文章功能是否启用等信息。返回一个包含状态（`active`, `inactive`, `disconnected`）、消息和详细信息的数组。
- **检查所有节点**: `check_all_nodes_status` 方法获取所有 `chiral_porter` 角色的用户，并逐个调用 `check_node_status`。为了避免短时间内大量请求，它会：
    - 如果不是强制检查 (`$force_check` 为 false)，且节点在过去一小时内已检查过，则使用缓存的状态。
    - 在检查多个节点时，每个请求之间会有2秒的延迟。
    - 检查结果会缓存到用户 meta (`_chiral_node_status_cache`) 中，并记录最后检查时间 (`_chiral_node_last_status_check`)。
- **定时任务**: 
    - `schedule_daily_checks()`: 在插件激活时设置一个每日运行的 WordPress cron job (`chiral_hub_daily_node_check`)。
    - `unschedule_daily_checks()`: 在插件停用时清除此 cron job。
    - `handle_daily_node_check()`: cron job 的回调函数。它会调度一个新的一次性事件 (`chiral_hub_staggered_node_check`)，并设置一个随机延迟（最多1小时），以错开实际的检查时间。
    - `handle_staggered_node_check()`: 实际执行 `check_all_nodes_status(false)` 的函数。
- **工具函数**: `get_node_url_from_porter` 用于获取节点 URL，`format_duration` 用于将秒数格式化为易读的时间描述。

### 2.12. Porter 管理界面 (`class-chiral-hub-porter-admin.php`)

`Chiral_Hub_Porter_Admin` 类为 `chiral_porter` 角色的用户提供了一个简化的后台管理界面。
- **添加菜单**: `add_porter_admin_menu` 方法为 Porter 用户添加一个名为 “My Chiral Data” (`porter-chiral-data`) 的顶级菜单项。
- **显示数据页面**: `display_porter_data_page` 方法渲染该菜单页面的内容。它会查询当前 Porter 用户创建的，或者其 `_chiral_node_id` meta 与 Porter 用户关联的 `_chiral_node_id` 相匹配的所有 `chiral_data` 文章，并以表格形式展示（标题、状态、日期、来源 URL、节点 ID、操作）。
    - **操作**: Porter 用户可以查看文章（链接到文章的前台页面）或删除文章。删除操作会进行 nonce 校验，并调用 `handle_delete_action`。
    - **删除限制**: “待审” (pending) 状态的文章不能直接在此界面删除，提示需要在 Node 端操作。
- **处理删除**: `handle_delete_action` 方法验证用户权限后，调用 `wp_delete_post` 永久删除文章，并重定向回列表页面，附带成功或失败的提示信息。
- **重定向标准 CPT 管理页面**: `redirect_porter_from_cpt_admin` 方法通过 `admin_init` 钩子，检查当前 Porter 用户是否试图访问标准的 `chiral_data` CPT 编辑列表 (`edit.php?post_type=chiral_data`) 或编辑页面 (`post.php?post=...&action=edit`)。如果是，则将他们重定向到 “My Chiral Data” 页面，防止他们使用 WordPress 的标准编辑界面。

### 2.13. 管理员设置界面 (`class-chiral-hub-admin.php`)

`Chiral_Hub_Admin` 类负责构建插件在 WordPress 后台的管理员设置页面。
- **资源加载**: `enqueue_styles` 和 `enqueue_scripts` 分别加载后台 CSS 和 JS 文件。
- **添加菜单**: `add_plugin_admin_menu` 方法添加了顶级菜单 “Chiral Hub” (`chiral-hub-core`) 和两个子菜单：“Settings” (指向主设置页) 和 “Node Management” (`chiral-hub-core-node-management`)。
- **显示页面**: `display_plugin_setup_page` 和 `display_node_management_page` 分别引入对应的视图文件 (`admin/views/settings-page.php` 和 `admin/views/node-management-page.php`) 来渲染页面内容。
- **注册设置**: `register_settings` 方法使用 WordPress Settings API 注册插件的选项组 (`chiral-hub-core_options_group`) 和选项名 (`chiral-hub-core_options`)。
    - **设置区域**: 定义了 “General Settings” 和 “Jetpack Integration” 两个设置区域。
    - **设置字段**: 
        - **Network Name**: 文本输入框，用于设置整个 Chiral 网络的名称或描述。
        - **Content Review Policy**: 下拉选择框，用于设置新节点提交内容时的默认状态（直接发布或待审）。
        - **Default Related Posts Count**: 数字输入框，设置 Jetpack 相关文章的默认数量。
        - **Enable Hub Transfer Mode**: 复选框，控制相关文章链接是指向 Hub（然后重定向）还是直接指向 Node。
    - **渲染字段**: 提供了 `render_network_name_field`, `render_new_porter_registration_field` 等方法来渲染各个设置字段的 HTML。
- **设置数据清理**: `sanitize_settings` 方法在保存设置时对输入数据进行清理。特别地，如果 “Network Name” 发生改变，它会调用 `auto_update_historical_posts_network_name` 方法。
- **自动更新历史文章网络名称**: `auto_update_historical_posts_network_name` 方法会遍历所有已存在的 `chiral_data` 文章，并将其 `chiral_network_name` meta 更新为新的网络名称。操作完成后会显示一个后台通知。
- **Jetpack 区域描述**: `render_jetpack_section_description` 提供了 Jetpack 集成区域的说明文字和指向 Jetpack 设置页面的链接。
- **AJAX 处理器**: 
    - `ajax_check_node_status`: 处理检查单个节点状态的 AJAX 请求。接收 `porter_id` 和 `nonce`，调用 `Chiral_Hub_Node_Checker::check_node_status`，缓存结果并返回 JSON 响应。
    - `ajax_check_all_nodes_status`: 处理检查所有节点状态的 AJAX 请求。接收 `nonce`，调用 `Chiral_Hub_Node_Checker::check_all_nodes_status(true)` (强制检查)，并返回 JSON 响应。

### 2.14. 公共功能 (`class-chiral-hub-public.php`)

`Chiral_Hub_Public` 类主要负责插件在网站前台的功能，对于 Hub Core 来说，这部分功能相对较少。
- **资源加载**: `enqueue_styles` 和 `enqueue_scripts` 方法被保留，但目前未加载任何特定的前台 CSS 或 JS，因为 Hub 主要通过重定向或 API 服务。
- **从搜索结果中排除 `chiral_data`**: `exclude_chiral_data_from_search` 方法通过 `pre_get_posts` 钩子，修改主搜索查询，将 `chiral_data` CPT 从搜索结果中排除。这确保了 Hub 站点的搜索功能不会直接显示这些聚合的内容，但它们仍然对 Jetpack 相关文章功能可见（因为 Jetpack 不依赖主搜索查询）。

## 3. 数据库与数据结构

### 3.1. WordPress Options

- **`chiral-hub-core_options`**: 存储插件的主要设置，是一个数组，包含以下键：
    - `network_name` (string): Chiral 网络的名称。
    - `new_porter_registration` (string): 新 Porter 节点提交内容的处理策略 (`default_status` 或 `pending`)。
    - `default_related_posts_count` (int): Jetpack 相关文章的默认数量。
    - `enable_hub_transfer_mode` (bool): 是否启用 Hub 传输模式 (链接通过 Hub 重定向)。

### 3.2. Custom Post Type: `chiral_data`

- **Post Fields**: 标准 WordPress 文章字段被用于：
    - `post_title`: 存储源文章的标题。
    - `post_content`: 存储源文章的内容 (通常是摘要或部分内容)。
    - `post_excerpt`: 存储源文章的摘要。
    - `post_author`: 存储创建此 `chiral_data` 条目的 `chiral_porter` 用户的 ID。
    - `post_date_gmt`: 存储源文章的发布日期 (GMT)。
    - `post_modified_gmt`: 存储源文章的最后修改日期 (GMT)。
    - `post_name` (slug): 自动生成的唯一标识符。
    - `post_status`: 根据 Porter 注册策略设置为 `publish` 或 `pending`。
- **Meta Fields**: 
    - `chiral_source_url` (string): 源文章的原始 URL。
    - `other_URLs` (string, JSON): 存储其他相关 URL，至少包含 `{"source":"<source_url>"}`。
    - `_chiral_node_id` (string): 来源 Chiral Connector 节点的唯一 ID。
    - `_chiral_data_original_post_id` (string): 源文章在其 Node 上的原始文章 ID。
    - `_chiral_data_original_title` (string): 源文章的原始标题 (备用)。
    - `_chiral_data_original_categories` (array, serialized): 源文章的分类名称数组。
    - `_chiral_data_original_tags` (array, serialized): 源文章的标签名称数组。
    - `_chiral_data_original_featured_image_url` (string): 源文章特色图片的 URL。
    - `_chiral_data_original_publish_date` (string, ISO 8601): 源文章的原始发布日期 (用于精确排序或显示)。
    - `chiral_network_name` (string): 该 `chiral_data` 条目所属的 Chiral 网络名称 (来自插件设置)。
    - `_thumbnail_id` (int): WordPress 特色图片的附件 ID。

### 3.3. User Meta (for `chiral_porter` users)

- `_chiral_node_id` (string): 该 Porter 用户关联的 Chiral Connector 节点的唯一 ID。
- `_chiral_node_url` (string): 该 Porter 用户关联的 Chiral Connector 节点的 URL (由 Node Checker 缓存)。
- `_chiral_node_status_cache` (array, serialized): Node Checker 对该 Porter 关联节点的最后检查结果缓存。
- `_chiral_node_last_status_check` (int, timestamp): Node Checker 最后检查该 Porter 关联节点的时间戳。

## 4. 关键业务流程与机制

### 4.1. Chiral Porter 注册与认证

- Porter 用户本质上是 WordPress 用户，拥有 `chiral_porter` 角色。
- 认证依赖 WordPress 的标准用户认证机制。Chiral Connector 在与 Hub 通信时，通常使用 Application Passwords 或其他安全的认证方式，代表一个 `chiral_porter` 用户进行操作。
- `_chiral_node_id` 用户 meta 将 Porter 用户与其来源节点关联起来。

### 4.2. 内容同步流程 (Node -> Hub)

1.  **Connector 发送数据**: Chiral Connector 插件在源站点（Node）上发布、更新或删除文章时，会向 Chiral Hub Core 的 WordPress REST API (`/wp/v2/chiral_data`) 发送请求。
    - **创建/更新**: 发送 `POST` 请求到 `/wp/v2/chiral_data` (创建) 或 `/wp/v2/chiral_data/{hub_post_id}` (更新)。请求体包含文章的各种信息（标题、内容、URL、节点 ID、原始文章 ID、元数据等）。
    - **删除**: 发送 `DELETE` 请求到 `/wp/v2/chiral_data/{hub_post_id}`。
2.  **Hub 接收与处理 (通过 `Chiral_Hub_Sync` 和相关钩子)**:
    - **权限检查**: WordPress REST API 首先验证请求是否来自合法的、拥有 `chiral_porter` 角色的用户，并且该用户有权操作 `chiral_data` CPT。
    - **状态强制**: `intercept_chiral_data_requests`, `verify_post_status_compliance`, `force_post_status_at_core_level` 等钩子函数会根据插件设置中的 “Content Review Policy” 强制设定新创建或更新的 `chiral_data` 文章的状态为 `publish` 或 `pending`。
    - **数据准备 (`handle_rest_pre_insert_chiral_data`)**: 
        - 生成唯一 slug。
        - 设置 `post_author`。
        - 更新 Porter 用户的 `_chiral_node_id` (如果需要)。
        - 确保 `other_URLs` 和 `_chiral_data_original_publish_date` meta 被正确设置。
    - **文章创建/更新**: WordPress 核心处理文章的插入或更新。
    - **后续处理 (`handle_rest_after_insert_chiral_data`)**: 
        - 确保 `chiral_network_name` meta 被设置。
        - 从 `_chiral_data_original_featured_image_url` 下载并设置特色图片。
    - **删除处理**: 如果是 `DELETE` 请求，`intercept_chiral_data_requests` 会调用 `wp_delete_post` 永久删除对应的 `chiral_data` 文章。

### 4.3. 相关文章获取与展示 (Jetpack)

1.  **Jetpack Sync**: Jetpack 的 Sync 模块会定期将 Hub 上的 `chiral_data` 文章（包括其白名单中的元数据，如 `chiral_network_name`, `chiral_source_url` 等）同步到 WordPress.com 的服务器。
2.  **相关性计算**: WordPress.com 的服务器利用这些数据（以及其他公开可访问的内容）来计算文章之间的相关性。
3.  **Connector 请求相关文章**: Chiral Connector 插件在其前端显示文章时，会向 Hub 的自定义 REST API 端点 `/chiral-hub/v1/related-data?post_id={hub_cpt_id}` 发送请求，其中 `{hub_cpt_id}` 是该文章在 Hub 上的 `chiral_data` CPT ID。
4.  **Hub 提供相关文章**: 
    - `Chiral_Hub_Rest_Api::get_related_data` 方法接收请求。
    - 调用 `Chiral_Hub_Jetpack::get_related_posts_for_id`，后者通过 `Jetpack_RelatedPosts::init()->get_for_post_id()` 从 Jetpack (实际上是从 WordPress.com 的缓存/索引) 获取与指定 `hub_cpt_id` 相关的一组 `chiral_data` 文章。
    - API 响应中包含这些相关文章的标题、链接、摘要、特色图片 URL 和来源网络名称。
    - **链接处理**: 
        - 如果 Hub 的 “Enable Hub Transfer Mode” 设置为 **启用**，则返回的链接会指向 Hub 上的 `chiral_data` 文章 (例如 `https://hub.example.com/chiral_data/related-post-slug/`)。当用户点击此链接时，会被 `Chiral_Hub_Redirect` 重定向到原始 Node 上的文章 URL。
        - 如果 Hub 的 “Enable Hub Transfer Mode” 设置为 **禁用**，则 `Chiral_Hub_Rest_Api::control_source_url_visibility_in_rest` 会确保 `chiral_source_url` 在标准 `/wp/v2/chiral_data` 响应中可见。此时，Connector 理论上可以直接从 Hub 获取原始 `chiral_source_url` 并生成直接指向 Node 的链接 (但这部分逻辑在 Connector 中实现，Hub 的 `/chiral-hub/v1/related-data` 端点返回的链接仍然是基于 Hub 的永久链接)。

### 4.4. Chiral Data 文章的访问与重定向

- 当用户直接通过浏览器访问 Hub 上的一个 `chiral_data` 文章的永久链接时 (例如 `https://hub.example.com/chiral_data/some-post-slug/`)：
- `Chiral_Hub_Redirect` 类的 `early_redirect_check` 或 `maybe_redirect_chiral_data_post` 方法会捕获此请求。
- `perform_redirect_if_needed` 方法会获取该文章的 `chiral_source_url` meta 值。
- 执行 301 永久重定向到 `chiral_source_url`。
- 这个重定向逻辑是**无条件**的，不受 “Enable Hub Transfer Mode” 设置的影响，避免聚合内容在 Hub 上直接暴露。

### 4.5. 节点状态监控

- 管理员可以在 Hub 后台的 “Node Management” 页面查看所有已连接节点（即所有 Chiral Porter 用户代表的节点）的状态。
- 页面加载时，会通过 AJAX 请求 (`ajax_check_all_nodes_status`) 或从缓存加载各节点状态。
- 管理员可以手动触发对单个节点 (`ajax_check_node_status`) 或所有节点的即时状态检查。
- 系统每日通过 cron job (`chiral_hub_daily_node_check` -> `chiral_hub_staggered_node_check`) 自动检查所有节点状态，并将结果缓存。
- 状态检查通过向节点的 `/wp-json/chiral-connector/v1/node-status` API 端点发送请求来完成。

## 5. 未来改进与注意事项

- **卸载流程**: 当前 `Chiral_Hub_Deactivator` 仅清除了 cron job 和刷新重写规则。完整的卸载脚本 (`uninstall.php`) 应考虑移除 Chiral Porter 角色、删除所有 `chiral_data` 文章、删除插件选项等，并提供相应的用户提示和选择。
- **安全性**: 虽然已有多层状态强制逻辑，但仍需持续关注 REST API 安全性，特别是权限校验和输入数据清理。
- **错误处理与日志**: 进一步增强错误处理机制，并提供更详细、可配置的日志记录功能，方便问题排查。
- **性能优化**: 对于大量节点和海量 `chiral_data` 文章的场景，需要关注数据库查询性能、REST API 响应时间以及 Jetpack Sync 的效率。
- **Hub Transfer Mode 的非一致性**: `Chiral_Hub_Redirect` 的无条件重定向与 `Chiral_Hub_Rest_Api` 中根据传输模式控制 `chiral_source_url` 可见性的逻辑之间，存在不一致。其目的在于，即便是在“Enable Hub Transfer Mode” 禁用时，也可能存在 `chiral_source_url` 被意外访问的情况。在这种情况下，我们希望 Chiral_Data 文章主体在 Hub 上不会意外暴露，因此重定向需要无条件被执行。
- **Porter 用户管理**: 考虑提供更完善的 Porter 用户管理界面，例如手动创建 Porter 用户、分配节点 ID、查看其活动日志等。
- **批量操作**: 在 Porter 管理界面和管理员的 Node Management 界面，可以考虑增加批量操作功能。

本文档提供了 Chiral Hub Core 插件的详细技术解析。随着插件的迭代，本文档也应相应更新。