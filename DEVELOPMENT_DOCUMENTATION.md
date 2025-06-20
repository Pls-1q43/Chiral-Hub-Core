# Chiral Hub Core & Chiral Connector 开发文档

## 1. 项目概述

本项目包含两个 WordPress 插件：Chiral Hub Core (简称 Hub) 和 Chiral Connector (简称 Connector)。它们协同工作，旨在实现跨站点相关文章推荐的功能。

*   **Chiral Hub Core**: 作为中心枢纽，负责接收、存储和管理来自多个 Connector 插件（安装在各个 Node 站点）的文章数据。它利用 Jetpack 将这些数据同步到 WordPress.com，以便利用其强大的相关文章计算能力。Hub 还提供 REST API 供 Connector 调用。
*   **Chiral Connector**: 安装在各个独立的 WordPress 站点（Node 站点）上。它负责将本站点的文章数据同步到 Hub，并从 Hub（间接通过 WordPress.com）获取相关文章，展示在 Node 站点的文章页面。

整体架构的核心思想是：Node 站点将内容元数据发送到 Hub -> Hub 存储并借助 Jetpack 将数据同步到 WordPress.com 进行索引 -> Node 站点请求相关文章时，Hub 查询 WordPress.com -> WordPress.com 返回相关内容 ID -> Hub 获取这些内容的详细信息并返回给 Node 站点 -> Node 站点展示相关内容。

## 2. Chiral Hub Core 插件

### 2.1 核心功能

*   接收并存储来自 Connector 插件的文章元数据。
*   管理一个名为 `chiral_data` 的自定义文章类型 (CPT) 来存储这些元数据。
*   集成 Jetpack，将 `chiral_data` CPT 的内容同步到 WordPress.com 以便进行相关性分析。
*   提供 REST API 端点供 Connector 插件进行数据同步和相关文章查询。
*   管理 `chiral_porter` 用户角色，用于授权 Connector 插件的访问。
*   提供后台管理界面，用于配置插件参数。

### 2.2 主要组件

*   **`chiral-hub-core.php`**: 插件主文件。负责定义常量、加载核心类、注册激活/停用钩子，并初始化插件。
*   **`includes/class-chiral-hub-core.php`**: 核心类。负责加载所有依赖项（如 CPT、REST API、角色管理、Jetpack 集成、同步逻辑等），设置国际化，并定义和运行 WordPress 钩子。
*   **`includes/class-chiral-hub-cpt.php`**: 定义 `chiral_data` 自定义文章类型。包括其标签、参数、支持的功能（如 `title`, `editor`, `excerpt`, `custom-fields`, `jetpack-related-posts`）以及注册相关的元字段。元字段主要包括：
    *   `_chiral_node_id`: 标识来源 Node 站点的 ID。
    *   `_chiral_source_url`: Node 站点原文的 URL。
    *   `_chiral_source_post_id`: Node 站点原文的文章 ID。
    *   `_chiral_source_title`: Node 站点原文的标题。
    *   `_chiral_source_content`: Node 站点原文的内容 (通常是摘要或部分内容)。
    *   `_chiral_source_excerpt`: Node 站点原文的摘要。
    *   `_chiral_source_featured_image_url`: Node 站点原文的特色图片 URL。
    *   `_chiral_network_name`: Hub 管理员设置的网络名称，用于前端展示。
*   **`includes/class-chiral-hub-rest-api.php`**: 实现自定义 REST API 端点。主要端点包括：
    *   `/wp-json/wp/v2/chiral_data`: 用于 Connector 创建、更新、删除 `chiral_data` 文章。权限与 `chiral_porter` 角色和 WordPress 内置 CPT 权限相结合。
    *   `/wp-json/chiral-hub/v1/related-data`: Connector 调用此端点获取相关文章。它接收 `source_url` (当前 Node 站点文章的 URL) 和 `requesting_node_id` (请求的 Node ID) 作为参数。
    *   `/wp-json/chiral-hub/v1/ping`: Connector 用于测试与 Hub 的连接状态。
*   **`includes/class-chiral-hub-roles.php`**: 定义 `chiral_porter` 用户角色。此角色拥有创建、编辑、删除其自身 `chiral_data` 文章的权限，以及管理其应用程序密码的权限。该类还严格限制 `chiral_porter` 用户在 WordPress 后台的访问范围，通常只允许访问个人资料页（用于管理应用程序密码）和自定义的文章列表页（查看其提交的 `chiral_data`）。
*   **`includes/class-chiral-hub-jetpack.php`**: 负责与 Jetpack 的集成，特别是其 Related Posts 模块。确保 `chiral_data` CPT 被 Jetpack 正确索引和用于相关文章推荐。它通过过滤器确保 CPT 支持 `jetpack-related-posts`，并被 Jetpack 的 REST API 和相关文章功能所允许。
*   **`includes/class-chiral-hub-sync.php`**: 处理来自 Connector 的数据同步请求的额外逻辑。关键功能是根据管理员在 Hub 设置中定义的“新 Porter 注册策略”来强制新提交的 `chiral_data` 文章的状态。例如，如果策略设置为“待审 (Pending)”，则所有新提交的文章状态会被强制改为 `pending`，即使 Connector 请求发布 (`publish`)。这是通过拦截 REST API 请求参数和使用 `wp_insert_post_data` 钩子在核心层面强制状态来实现的。
*   **`admin/class-chiral-hub-admin.php`**: 实现 Hub 插件的后台设置页面。管理员可以在此配置：
    *   网络名称/描述。
    *   新 Porter 注册策略 (例如，新文章默认为 `publish` 或 `pending`)。
    *   默认相关文章数量。
    *   启用/禁用 Hub 传输模式等。
*   **`public/class-chiral-hub-public.php`**: 处理面向公众的逻辑。一个主要功能是通过 `pre_get_posts` 钩子从 Hub 站点的常规搜索结果中排除 `chiral_data` 类型的文章，因为这些文章仅用于内部数据同步和相关性计算，不应直接展示给 Hub 站点的访问者。

### 2.3 业务逻辑关键点

*   **Porter 用户与认证**: Connector 插件通过一个在 Hub 上创建的 `chiral_porter` 角色的用户进行认证。认证方式是使用 WordPress 的应用程序密码 (Application Passwords) 功能。Node 站点管理员在 Connector 设置中输入 Hub 的 URL、`chiral_porter` 用户名和该用户生成的应用程序密码。
*   **`chiral_data` CPT**: 这是 Hub 端存储所有 Node 站点文章元数据的核心。每个 `chiral_data` 文章代表一个来自 Node 站点的原文。
*   **Jetpack 同步**: Hub 依赖 Jetpack 将 `chiral_data` CPT 的内容同步到 WordPress.com 的全局数据中心。这是实现跨站相关文章推荐的基础，因为 WordPress.com 会对这些数据进行索引和分析。
*   **REST API 权限**: API 端点严格控制权限。通常，`chiral_porter` 用户只能创建、读取、更新或删除与他们自己的 `_chiral_node_id` 相关联的 `chiral_data` 文章。
*   **Porter 注册策略**: 管理员可以定义新 Connector (Porter) 提交的文章是直接发布还是需要审核。这通过 `class-chiral-hub-sync.php` 中的逻辑强制执行。

## 3. Chiral Connector 插件

### 3.1 核心功能

*   安装在各个独立的 WordPress 站点（Node 站点）上。
*   将 Node 站点的文章（创建、更新、删除）同步到 Chiral Hub Core 插件。
*   从 Hub (间接通过 `public-api.wordpress.com`) 获取与当前文章相关的文章列表。
*   在 Node 站点的文章页面（内容之后或通过短代码）显示这些相关文章。
*   提供后台管理界面，用于配置与 Hub 的连接参数、同步选项和显示选项。

### 3.2 主要组件

*   **`chiral-connector.php`**: 插件主文件。定义常量、加载核心类、注册激活/停用钩子，并初始化插件。
*   **`includes/class-chiral-connector-core.php`**: 核心类。负责加载所有依赖项（如 API 通信、同步逻辑、显示逻辑、后台管理等），设置国际化，并定义和运行 WordPress 钩子。
*   **`includes/class-chiral-connector-api.php`**: 处理所有与 Chiral Hub 的 API 通信。主要方法包括：
    *   `send_data_to_hub()`: 将文章数据（标题、内容、摘要、特色图片 URL、原文链接、Node ID 等）通过 POST 请求发送到 Hub 的 `/wp/v2/chiral_data` 端点。如果 Hub 端已存在对应文章（基于 `node_id` 和 `source_post_id`），则会更新。
    *   `delete_data_from_hub()`: 当 Node 站点的文章被删除时，向 Hub 发送 DELETE 请求，删除对应的 `chiral_data` 文章。
    *   `get_related_data_from_hub()`: 这是获取相关文章的核心方法。它向 Hub 的 `/wp-json/chiral-hub/v1/related-data` 端点发送 GET 请求，参数包括当前文章的 URL (`source_url`) 和 Node ID (`requesting_node_id`)。Hub 会处理此请求，查询 `public-api.wordpress.com` 并返回相关文章数据。
    *   `ping_hub()`: 测试与 Hub 的连接。
*   **`includes/class-chiral-connector-sync.php`**: 监听 Node 站点上的文章事件（如 `save_post`, `publish_post`, `trash_post`, `delete_post`）。当这些事件发生时，它会收集文章数据并调用 `Chiral_Connector_Api` 中的相应方法与 Hub 同步。还包含一个批量同步功能，允许将现有文章批量发送到 Hub。
*   **`includes/class-chiral-connector-display.php`**: 负责准备在前端显示相关文章的 HTML 占位符。它通过 `the_content` 过滤器自动在文章末尾添加占位符，或通过 `[chiral_related_posts]` 短代码手动插入。这些占位符包含必要的数据属性（如当前文章 URL, Node ID, Hub URL, 显示数量），供前端 JavaScript 使用。
*   **`admin/class-chiral-connector-admin.php`**: 实现 Connector 插件的后台设置页面。用户可以在此配置：
    *   Hub URL。
    *   Hub 用户名 (应为 `chiral_porter` 角色)。
    *   Hub 应用程序密码。
    *   Node ID (用于标识此 Connector 实例)。
    *   同步选项 (例如，选择哪些文章类型进行同步)。
    *   显示选项 (例如，是否启用相关文章显示，显示数量，显示标题等)。
    *   提供“测试连接”、“触发批量同步”、“清除缓存”、“退出网络”等操作按钮。
    *   还负责在文章编辑页面添加 “Send to Chiral?” meta box，允许用户控制单篇文章是否同步到 Hub。
*   **`public/class-chiral-connector-public.php`**: 注册面向公众的脚本和样式。它初始化 `Chiral_Connector_Display` 实例，并注册相关的过滤器 (如 `the_content`) 和短代码。关键是它注册了 AJAX 动作 `wp_ajax_nopriv_chiral_fetch_related_posts` 和 `wp_ajax_chiral_fetch_related_posts`，这些动作会调用 `Chiral_Connector_Display` 中的 `ajax_fetch_related_posts` 方法。
*   **`public/assets/js/chiral-connector-public.js`**: 前端 JavaScript 文件。当页面加载时，它会查找由 `Chiral_Connector_Display` 生成的 HTML 占位符。然后，它通过 AJAX 向 `class-chiral-connector-public.php` 注册的 WordPress AJAX 端点 (`ajax_fetch_related_posts`) 发送请求，以获取相关文章数据。获取到数据后，JavaScript 负责将这些数据渲染成 HTML 列表并插入到占位符中。

### 3.3 业务逻辑关键点

*   **数据同步**: 当 Node 站点上的文章被创建、更新或删除时，`Chiral_Connector_Sync` 会捕获这些事件，并使用 `Chiral_Connector_Api` 将更改同步到 Hub。同步的数据包括文章标题、内容（或摘要）、特色图片 URL、原文永久链接以及 Node ID。
*   **相关文章获取流程**:
    1.  用户访问 Node 站点上的一篇文章。
    2.  `Chiral_Connector_Display` (通过 `the_content` 过滤器或短代码) 在页面上输出一个 HTML 占位符，其中包含当前文章的 URL、Node ID 等数据属性。
    3.  `chiral-connector-public.js` 在文档加载完成后执行，找到该占位符。
    4.  JavaScript 向 WordPress AJAX 端点 (`admin-ajax.php`，动作是 `chiral_fetch_related_posts`) 发送一个 POST 请求。请求参数包括当前文章的 URL (`source_url`)、Node ID (`requesting_node_id`) 和期望的相关文章数量。
    5.  在 Connector 后端，`Chiral_Connector_Public` 捕获此 AJAX 请求，并调用 `Chiral_Connector_Display` 的 `ajax_fetch_related_posts` 方法。
    6.  `ajax_fetch_related_posts` 方法进而调用 `Chiral_Connector_Api` 的 `get_related_data_from_hub` 方法。
    7.  `get_related_data_from_hub` 向 Hub 的 `/wp-json/chiral-hub/v1/related-data` 端点发送一个 GET 请求，传递 `source_url` 和 `requesting_node_id`。
    8.  在 Hub 端，`Class_Chiral_Hub_Rest_Api` 的 `get_related_data_for_node_item` 方法处理此请求。它首先根据 `source_url` 和 `requesting_node_id` 在其 `chiral_data` CPT 中查找对应的文章。这个 `chiral_data` 文章的 ID (我们称之为 `hub_post_id`) 是关键。
    9.  Hub 接着构造一个请求到 WordPress.com 的公共 API，通常是类似 `https://public-api.wordpress.com/rest/v1.1/sites/$site_identifier/posts/$hub_post_id/related` 的 URL。其中 `$site_identifier` 是 Hub 站点的 WordPress.com 站点标识符，`$hub_post_id` 就是上一步找到的 `chiral_data` 文章的 ID。
    10. WordPress.com 返回与该 `hub_post_id` 相关的文章列表（这些通常也是 `chiral_data` 类型的文章，代表其他 Node 站点的原文）。
    11. Hub 接收到来自 WordPress.com 的数据，提取所需信息（如标题、链接、特色图片、来源网络名称等），并将其格式化后作为响应返回给 Connector 的 `get_related_data_from_hub` 方法。
    12. Connector 的 `get_related_data_from_hub` 方法将这些数据返回给 `ajax_fetch_related_posts`，后者再将其作为 JSON 响应返回给前端的 JavaScript。
    13. 前端 JavaScript 收到 JSON 数据后，动态生成 HTML 来展示相关文章列表，并替换掉之前的“加载中”占位符。
*   **Node ID**: 在 Connector 的设置中手动配置。这个 ID 用于 Hub 区分来自不同 Node 站点的数据。它会作为元数据 `_chiral_node_id` 存储在 Hub 的 `chiral_data` 文章中。
*   **`Send to Chiral?` Meta Box**: 允许编辑在文章编辑页面选择是否将当前文章同步到 Hub。这个设置存储为文章的元数据 `_chiral_send_to_hub`。
*   **退出网络**: Connector 提供一个功能，允许 Node 站点管理员请求 Hub 删除与此 Node ID 相关的所有数据。它还会清除 Connector 本地的配置信息并停用插件。

## 4. 数据流总结

1.  **内容同步 (Connector -> Hub -> WordPress.com)**:
    *   Node 站点 (Connector) 文章发生变化 (增/改/删)。
    *   Connector 将文章元数据 (URL, title, excerpt, featured image, node_id 等) 发送到 Hub 的 `/wp/v2/chiral_data` REST API 端点。
    *   Hub 创建或更新对应的 `chiral_data` CPT 文章。
    *   Jetpack (在 Hub 上运行) 检测到 `chiral_data` 文章的变化，并将其同步到 WordPress.com 进行索引。

2.  **相关文章请求与展示 (User -> Connector -> Hub -> WordPress.com -> Hub -> Connector -> User)**:
    *   用户在 Node 站点上查看一篇文章。
    *   Connector 前端 JS 向 Connector 后端 AJAX 端点请求相关文章，提供当前文章 URL 和 Node ID。
    *   Connector 后端向 Hub 的 `/chiral-hub/v1/related-data` API 端点请求相关文章。
    *   Hub 根据 Node 文章 URL 和 Node ID 找到对应的 `chiral_data` 文章 ID (`hub_post_id`)。
    *   Hub 使用 `hub_post_id` 向 `public-api.wordpress.com` 的相关文章端点查询。
    *   WordPress.com 返回相关的 `chiral_data` 文章列表 (通常是 ID 和一些基本信息)。
    *   Hub 获取这些相关 `chiral_data` 文章的详细信息 (如存储的源 URL、标题、特色图等)。
    *   Hub 将格式化后的相关文章数据列表返回给 Connector。
    *   Connector 后端将数据返回给前端 JS。
    *   Connector 前端 JS 将数据显示为相关文章列表。

## 5. 弃用代码

*   **`includes/class-chiral-connector-api.php`**: 内部存在一个名为 `get_related_data_from_hub_direct()` 的方法。根据代码注释和逻辑，这似乎是一个较早版本的相关文章获取方法，它直接尝试从 Hub 的 `/wp/v2/chiral_data` 端点获取相关内容，而不是通过 Hub 查询 `public-api.wordpress.com`。当前激活的逻辑是 `get_related_data_from_hub()`，它通过 Hub 的自定义端点 `/chiral-hub/v1/related-data` 来实现，后者再与 `public-api.wordpress.com` 交互。因此，`get_related_data_from_hub_direct()` 可以被认为是已弃用或备用逻辑。

## 6. 非直觉代码说明 (基于注释和逻辑分析)

*   **`Chiral_Hub_Sync::force_post_status_at_core_level()` (Hub)**: 此方法使用 `wp_insert_post_data` WordPress 核心钩子。它的目的是在文章数据即将存入数据库之前，强制修改 `chiral_data` 类型文章的 `post_status`。这是为了确保即使 `chiral_porter` 用户通过 REST API 请求以特定状态（如 `publish`）创建文章，如果 Hub 管理员设置了“新 Porter 注册策略”为“待审 (`pending`)”，那么文章状态依然会被强制改为 `pending`。这是一个深层次的安全和策略执行机制，防止绕过管理员设置。
*   **`Chiral_Hub_Jetpack::force_register_filters()` (Hub)**: 此方法用于确保 Jetpack 相关的 WordPress 过滤器 (如 `rest_api_allowed_post_types`, `jetpack_relatedposts_filter_post_type`) 被正确添加，并且指向本插件的回调函数。注释中提到“不要移除所有现有过滤器，而是确保我们的过滤器被正确添加”，并检查过滤器是否已存在，避免重复添加。这可能是为了应对其他插件或主题可能无意中移除或修改这些重要过滤器的情况，从而保证 Jetpack 功能对 `chiral_data` CPT 的正常运作。
*   **`Chiral_Connector_Admin::ajax_quit_network()` (Connector)**: 当 Node 站点管理员选择“退出网络”时，此 AJAX 处理函数会向 Hub 发送请求，尝试删除与该 Node ID 相关的所有数据。然而，代码注释中明确指出：“After this process, you MUST manually log in to your Chiral Hub account to verify that all your data has been removed. The plugin will attempt to delete the data, but verification is your responsibility.” 这说明插件尽力而为，但最终的数据清理确认责任在用户。
*   **Node ID 唯一性 (Connector/Hub)**: 虽然 `node_id` 用于标识一个 Node 站点，但插件本身（尤其是在 Connector 端）似乎不强制其全局唯一性。Hub 端通过 `node_id` 和 `source_post_id` 的组合来唯一确定一篇来自特定 Node 的文章。管理员在设置 `node_id` 时应确保其对于当前 Hub 实例是唯一的，以避免数据混淆。
*   **相关文章来源显示逻辑 (`chiral-connector-public.js` in Connector)**: 在前端展示相关文章时，每个条目的来源信息显示逻辑如下：
    1.  优先使用 `post.author_name` (如果存在且不为 'N/A')，格式为 “Source: {author_name}”。
    2.  如果 `author_name` 不可用，但文章 URL (`post.url`) 指向配置的 Hub URL 并且路径包含 `/chiral_data/`，则来源显示为 Hub 的域名，格式为 “Source: {hub_hostname} (Hub)”。
    3.  如果以上两者都不可用，但文章有有效的源 URL (`sourceUrl`)，则来源显示为该源 URL 的域名，格式为 “Source: {source_hostname}”。
    4.  整个相关文章列表的副标题会显示网络名称（如果从第一篇文章的 `network_name` 字段获取到）或 Hub 的域名，并链接到 Hub URL。
*   **`Chiral_Hub_Roles::restrict_porter_dashboard_access()` (Hub)**: 此方法严格限制 `chiral_porter` 用户在 WordPress 后台的访问。除了他们自己的个人资料页面（用于管理应用程序密码）和为他们特设的 `chiral_data` 列表页面外，尝试访问其他后台页面都会被重定向。这是为了安全和简化 Porter 用户的操作界面。