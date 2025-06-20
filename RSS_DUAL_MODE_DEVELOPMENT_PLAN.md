# Chiral Hub RSS双模式支持开发计划

## 📋 **项目概述**

本文档详细规划了为Chiral Hub Core系统增加RSS模式支持的完整开发方案。该方案将在保持现有WordPress模式完全兼容的基础上，为静态博客用户提供等价的相关文章推荐服务。

### 🎯 **目标**
- **向后兼容**：现有WordPress用户体验不受任何影响
- **功能等价**：RSS模式用户获得相同质量的相关文章推荐
- **统一管理**：Hub管理员可在同一界面管理两种模式的节点
- **可扩展性**：为未来支持更多静态博客平台奠定基础

---

## 🏗️ **系统架构扩展**

### 现有架构保持不变
```
WordPress Node ←→ Chiral Connector Plugin ←→ Hub Core ←→ Jetpack ←→ WordPress.com
```

### 新增RSS架构
```
Static Blog ←→ RSS/Sitemap ←→ Hub Core (RSS Crawler) ←→ Jetpack ←→ WordPress.com
                              ↑
                      统一的chiral_data CPT
                              ↓
                    Static JS Client ←→ Hub API
```

---

## 👤 **Porter用户自助配置设计**

### 用户体验流程

#### 静态博客用户完整流程：
```
1. Hub管理员创建Porter账号 → 
2. Porter收到登录信息 → 
3. Porter登录Hub后台 → 
4. Porter看到自己的专属配置页面 → 
5. Porter选择"RSS模式" → 
6. Porter填写RSS URL和Sitemap URL → 
7. Porter点击"测试连接" → 
8. Porter点击"开始批量导入" → 
9. Porter实时监控导入进度 → 
10. Porter配置完成，开始享受相关文章推荐
```

#### WordPress用户保持原有流程：
```
1. Hub管理员创建Porter账号 → 
2. Porter在自己的WordPress站点安装Connector插件 → 
3. Porter在插件中配置Hub连接信息 → 
4. 自动同步开始工作
```

### Porter专属后台界面设计

Porter用户登录Hub后台时，会看到专门为他们设计的界面，而不是复杂的管理员界面。

### 6. Porter自助配置的具体用户体验

#### RSS模式Porter的完整使用流程：

**第一步：Hub管理员创建账号**
- 管理员在Hub后台创建Porter用户
- 设置用户名、邮箱和初始密码
- 系统自动分配`chiral_porter`角色

**第二步：Porter首次登录**
```
Porter访问 https://hub.example.com/wp-admin/ 
→ 输入账号密码登录 
→ 系统自动跳转到现有的 "My Chiral Data" 页面
→ Porter看到带有"配置"和"数据"两个标签的界面
```

**第三步：选择RSS模式**
- Porter看到两个选项卡：⚡ WordPress模式 和 📄 RSS模式
- Porter点击选择"RSS模式"
- 界面切换显示RSS配置表单

**第四步：配置RSS信息**
```php
// Porter需要填写的信息：
RSS Feed URL:    https://myblog.com/feed.xml
Sitemap URL:     https://myblog.com/sitemap.xml
Sync Frequency:  每小时 / 每日
```

**第五步：测试连接**
- Porter点击"测试连接"按钮
- 系统验证RSS和Sitemap是否可访问
- 显示测试结果：发现的RSS条目数、Sitemap中的URL数

**第六步：保存配置**
- Porter点击"保存配置"
- 系统保存所有设置到用户meta

**第七步：开始批量导入**
- Porter点击"开始批量导入"按钮
- 系统启动Sitemap解析和批量导入任务
- Porter看到实时进度条和导入统计

**第八步：监控进度**
```
导入进度: ████████████░░░░░░░░ 65% (156/240)
当前处理: /posts/article-156.html

导入统计:
├ 成功: 145篇
├ 失败: 11篇  
└ 预计剩余: 5分钟
```

**第九步：完成配置**
- 导入完成后，Porter的博客内容已经同步到Hub
- Porter可以在自己的静态博客中集成Chiral客户端
- 开始享受相关文章推荐功能

#### Porter后续日常使用：

**查看同步状态**
- Porter可以随时登录Hub查看自己的数据同步状态
- 查看最近的同步日志和错误信息
- 手动触发同步（如果需要）

**修改配置**
- Porter可以随时更改RSS URL或Sitemap URL
- 调整同步频率
- 重新测试连接

**数据管理**
- 查看已同步的文章列表
- 删除不需要的同步文章
- 查看同步统计和历史

### 7. 集成到现有"My Chiral Data"面板的界面设计

Porter用户登录后看到的界面结构：

```html
<!-- My Chiral Data 页面标题 -->
<h1>My Chiral Data</h1>

<!-- 标签页导航 -->
<nav class="nav-tab-wrapper">
    <a href="?page=porter-chiral-data&tab=data" class="nav-tab nav-tab-active">
        📊 My Data
    </a>
    <a href="?page=porter-chiral-data&tab=config" class="nav-tab">
        ⚙️ Configuration  
    </a>
</nav>

<!-- 数据标签页内容（现有功能） -->
<div id="data-tab" class="tab-content">
    <!-- 现有的文章列表展示 -->
    <!-- 同步状态显示 -->
    <!-- 操作按钮等 -->
</div>

<!-- 配置标签页内容（新增功能） -->
<div id="config-tab" class="tab-content">
    <!-- 同步模式选择 -->
    <!-- RSS配置表单 -->  
    <!-- 测试连接按钮 -->
    <!-- 批量导入进度 -->
</div>
```

### 8. 管理员视角的变化

Hub管理员的工作量大大减少：

**之前需要做的：**
- 手动为每个Porter配置RSS模式
- 手动填写RSS URL和Sitemap URL
- 手动触发批量导入
- 处理Porter的配置变更请求

**现在只需要做的：**
- 创建Porter账号并告知登录信息
- 可选：在节点管理页面查看所有Porter的状态概览
- 处理技术问题（如果Porter遇到导入失败等）

**Porter用户体验：**
- 保持现有的"My Chiral Data"菜单，无需学习新界面
- 在熟悉的数据管理页面中增加配置功能
- 一个页面完成所有操作：查看数据 + 管理配置

---

## 📊 **数据库扩展设计**

### 1. Porter用户元数据扩展

在现有`_chiral_node_id`基础上增加：

```php
// 新增用户元数据字段
'_chiral_sync_mode'               => 'wordpress|rss'    // 同步模式
'_chiral_rss_url'                 => string             // RSS feed URL
'_chiral_sitemap_url'             => string             // Sitemap URL
'_chiral_rss_sync_frequency'      => 'hourly|daily'     // 同步频率
'_chiral_rss_crawl_strategy'      => 'smart|aggressive' // 巡检策略
'_chiral_rss_last_sync'           => timestamp          // 上次同步时间
'_chiral_rss_sync_errors'         => json_array         // 同步错误记录
'_chiral_rss_discovered_urls'     => json_array         // 发现的URL列表缓存
```

### 2. chiral_data CPT元数据扩展

在现有元数据基础上增加：

```php
// 源类型标识
'_chiral_source_type'             => 'wordpress|rss'    // 数据来源类型
'_chiral_sync_method'             => 'plugin|sitemap|rss_crawl|manual' // 同步方法
'_chiral_rss_entry_guid'          => string             // RSS条目的GUID
'_chiral_content_hash'            => string             // 内容哈希（检测更新）
'_chiral_last_crawl_check'        => timestamp          // 上次巡检时间
'_chiral_crawl_priority'          => int                // 巡检优先级（基于年龄）
```

### 3. 新增数据库表：chiral_rss_queue

```sql
CREATE TABLE `{prefix}chiral_rss_queue` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `porter_user_id` bigint(20) UNSIGNED NOT NULL,
    `action_type` varchar(20) NOT NULL, -- 'initial_import', 'rss_update', 'content_check'
    `target_url` text NOT NULL,
    `priority` tinyint(3) UNSIGNED NOT NULL DEFAULT 5, -- 1-10, 1最高
    `status` varchar(20) NOT NULL DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'failed'
    `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
    `scheduled_time` datetime NOT NULL,
    `completed_time` datetime NULL,
    `error_message` text NULL,
    `metadata` longtext NULL, -- JSON格式存储额外信息
    PRIMARY KEY (`id`),
    KEY `porter_status` (`porter_user_id`, `status`),
    KEY `scheduled_priority` (`scheduled_time`, `priority`),
    FOREIGN KEY (`porter_user_id`) REFERENCES `{prefix}users` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔧 **核心组件开发计划**

### 1. 创建 `class-chiral-hub-rss-crawler.php`

这是RSS模式的核心处理器，负责所有RSS相关的数据获取和处理。

#### 主要方法设计：

```php
class Chiral_Hub_RSS_Crawler {
    
    /**
     * 执行Sitemap批量导入
     * @param int $porter_user_id Porter用户ID
     * @param string $sitemap_url Sitemap URL
     * @return array 导入结果统计
     */
    public function batch_import_from_sitemap( $porter_user_id, $sitemap_url );
    
    /**
     * 处理RSS增量更新
     * @param int $porter_user_id Porter用户ID
     * @return array 更新结果
     */
    public function process_rss_updates( $porter_user_id );
    
    /**
     * 智能内容巡检
     * @param int $porter_user_id Porter用户ID
     * @param string $strategy 巡检策略
     * @return array 巡检结果
     */
    public function smart_content_patrol( $porter_user_id, $strategy = 'smart' );
    
    /**
     * 解析单个页面内容
     * @param string $url 页面URL
     * @return array|WP_Error 解析结果
     */
    private function parse_page_content( $url );
    
    /**
     * 从HTML提取元数据
     * @param string $html HTML内容
     * @param string $url 页面URL
     * @return array 元数据数组
     */
    private function extract_metadata_from_html( $html, $url );
}
```

#### 智能巡检策略：

```php
/**
 * 根据文章年龄确定巡检频率
 */
private function calculate_crawl_priority( $publish_date ) {
    $days_old = ( time() - strtotime( $publish_date ) ) / DAY_IN_SECONDS;
    
    if ( $days_old <= 7 ) return 1;        // 一周内 - 每日检查
    if ( $days_old <= 30 ) return 3;       // 一月内 - 每3天
    if ( $days_old <= 90 ) return 7;       // 三月内 - 每周
    if ( $days_old <= 365 ) return 30;     // 一年内 - 每月
    
    return 90; // 一年以上 - 每季度
}
```

### 2. 创建 `class-chiral-hub-rss-queue.php`

任务队列管理器，处理异步RSS处理任务。

```php
class Chiral_Hub_RSS_Queue {
    
    /**
     * 添加任务到队列
     */
    public function enqueue_task( $porter_user_id, $action_type, $target_url, $priority = 5, $metadata = array() );
    
    /**
     * 处理队列中的任务
     */
    public function process_queue( $batch_size = 10 );
    
    /**
     * 获取特定Porter的队列状态
     */
    public function get_porter_queue_status( $porter_user_id );
    
    /**
     * 清理已完成的任务
     */
    public function cleanup_completed_tasks( $days_old = 7 );
}
```

### 3. 扩展 `class-chiral-hub-admin.php`

在现有管理界面中增加RSS模式配置。

#### 新增设置字段：

```php
// 在register_settings方法中添加
add_settings_field(
    'rss_mode_settings',
    __( 'RSS Mode Settings', 'chiral-hub-core' ),
    array( $this, 'render_rss_mode_section' ),
    $this->plugin_name,
    $this->plugin_name . '_general_settings'
);
```

#### RSS模式设置界面：

```php
public function render_rss_mode_section() {
    $options = get_option( $this->plugin_name . '_options' );
    ?>
    <fieldset>
        <label>
            <input type="checkbox" name="<?php echo $this->plugin_name; ?>_options[enable_rss_mode]" 
                   value="1" <?php checked( 1, $options['enable_rss_mode'] ?? 0 ); ?>>
            <?php _e( 'Enable RSS Mode Support', 'chiral-hub-core' ); ?>
        </label>
        <p class="description">
            <?php _e( 'Allow static blog sites to connect via RSS feeds and Sitemaps.', 'chiral-hub-core' ); ?>
        </p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e( 'Default RSS Sync Frequency', 'chiral-hub-core' ); ?></th>
                <td>
                    <select name="<?php echo $this->plugin_name; ?>_options[default_rss_frequency]">
                        <option value="hourly" <?php selected( 'hourly', $options['default_rss_frequency'] ?? 'hourly' ); ?>>
                            <?php _e( 'Hourly', 'chiral-hub-core' ); ?>
                        </option>
                        <option value="daily" <?php selected( 'daily', $options['default_rss_frequency'] ?? 'hourly' ); ?>>
                            <?php _e( 'Daily', 'chiral-hub-core' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Content Patrol Strategy', 'chiral-hub-core' ); ?></th>
                <td>
                    <select name="<?php echo $this->plugin_name; ?>_options[patrol_strategy]">
                        <option value="smart" <?php selected( 'smart', $options['patrol_strategy'] ?? 'smart' ); ?>>
                            <?php _e( 'Smart (Age-based frequency)', 'chiral-hub-core' ); ?>
                        </option>
                        <option value="aggressive" <?php selected( 'aggressive', $options['patrol_strategy'] ?? 'smart' ); ?>>
                            <?php _e( 'Aggressive (Check all regularly)', 'chiral-hub-core' ); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>
    </fieldset>
    <?php
}
```

### 4. 扩展现有的 `class-chiral-hub-porter-admin.php`

不新增菜单，而是将配置功能集成到现有的"My Chiral Data"面板中，让Porter用户在一个地方完成所有操作。

```php
// 扩展现有的 Porter 管理类
class Chiral_Hub_Porter_Admin {
    
    /**
     * 修改现有的Porter菜单，添加配置标签
     */
    public function add_porter_admin_menu() {
        add_menu_page(
            __( 'My Chiral Data', 'chiral-hub-core' ),
            __( 'My Chiral Data', 'chiral-hub-core' ),
            Chiral_Hub_Roles::ROLE_SLUG,
            'porter-chiral-data',
            array( $this, 'display_porter_data_page' ),
            'dashicons-networking',
            30
        );
    }
    
    /**
     * 显示Porter数据页面（集成配置功能）
     */
    public function display_porter_data_page() {
        $user_id = get_current_user_id();
        
        // 获取当前用户的同步配置
        $sync_mode = get_user_meta( $user_id, '_chiral_sync_mode', true ) ?: 'wordpress';
        $node_id = get_user_meta( $user_id, '_chiral_node_id', true );
        $rss_url = get_user_meta( $user_id, '_chiral_rss_url', true );
        $sitemap_url = get_user_meta( $user_id, '_chiral_sitemap_url', true );
        $sync_frequency = get_user_meta( $user_id, '_chiral_rss_sync_frequency', true ) ?: 'hourly';
        
        // 检查当前选择的标签页
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'data';
        
        include CHIRAL_HUB_CORE_PLUGIN_DIR . 'admin/views/porter-data-page.php';
    }
    
    /**
     * 处理Porter的配置保存
     */
    public function handle_config_save() {
        if ( ! isset( $_POST['chiral_porter_config_nonce'] ) || 
             ! wp_verify_nonce( $_POST['chiral_porter_config_nonce'], 'save_porter_config' ) ) {
            wp_die( __( 'Security check failed.', 'chiral-hub-core' ) );
        }
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'chiral-hub-core' ) );
        }
        
        $user_id = get_current_user_id();
        $sync_mode = sanitize_text_field( $_POST['sync_mode'] );
        
        update_user_meta( $user_id, '_chiral_sync_mode', $sync_mode );
        
        if ( $sync_mode === 'rss' ) {
            update_user_meta( $user_id, '_chiral_rss_url', esc_url_raw( $_POST['rss_url'] ) );
            update_user_meta( $user_id, '_chiral_sitemap_url', esc_url_raw( $_POST['sitemap_url'] ) );
            update_user_meta( $user_id, '_chiral_rss_sync_frequency', sanitize_text_field( $_POST['sync_frequency'] ) );
        }
        
        wp_redirect( add_query_arg( 'updated', '1', wp_get_referer() ) );
        exit;
    }
    
    /**
     * AJAX: 测试RSS连接
     */
    public function ajax_test_rss_connection() {
        check_ajax_referer( 'chiral_porter_ajax', 'nonce' );
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'chiral-hub-core' ) ) );
        }
        
        $rss_url = esc_url_raw( $_POST['rss_url'] );
        $sitemap_url = esc_url_raw( $_POST['sitemap_url'] );
        
        $rss_crawler = new Chiral_Hub_RSS_Crawler( 'chiral-hub-core', CHIRAL_HUB_CORE_VERSION );
        $test_result = $rss_crawler->test_connection( $rss_url, $sitemap_url );
        
        if ( is_wp_error( $test_result ) ) {
            wp_send_json_error( array( 
                'message' => $test_result->get_error_message()
            ) );
        }
        
        wp_send_json_success( array(
            'message' => __( 'Connection test successful!', 'chiral-hub-core' ),
            'rss_items' => $test_result['rss_items'],
            'sitemap_urls' => $test_result['sitemap_urls']
        ) );
    }
    
    /**
     * AJAX: 开始Sitemap导入
     */
    public function ajax_start_sitemap_import() {
        check_ajax_referer( 'chiral_porter_ajax', 'nonce' );
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'chiral-hub-core' ) ) );
        }
        
        $user_id = get_current_user_id();
        $sitemap_url = get_user_meta( $user_id, '_chiral_sitemap_url', true );
        
        if ( empty( $sitemap_url ) ) {
            wp_send_json_error( array( 'message' => __( 'Please save your Sitemap URL first.', 'chiral-hub-core' ) ) );
        }
        
        $rss_crawler = new Chiral_Hub_RSS_Crawler( 'chiral-hub-core', CHIRAL_HUB_CORE_VERSION );
        $import_result = $rss_crawler->initiate_sitemap_import( $user_id, $sitemap_url );
        
        if ( is_wp_error( $import_result ) ) {
            wp_send_json_error( array( 'message' => $import_result->get_error_message() ) );
        }
        
        wp_send_json_success( array(
            'message' => __( 'Sitemap import started!', 'chiral-hub-core' ),
            'import_id' => $import_result['import_id'],
            'total_urls' => $import_result['total_urls']
        ) );
    }
    
    /**
     * AJAX: 获取导入进度
     */
    public function ajax_get_import_progress() {
        check_ajax_referer( 'chiral_porter_ajax', 'nonce' );
        
        if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'chiral-hub-core' ) ) );
        }
        
        $user_id = get_current_user_id();
        $import_status = get_user_meta( $user_id, '_chiral_import_status', true );
        
        wp_send_json_success( $import_status );
    }
}
```

### 5. Porter权限和菜单重定向

需要修改现有的Porter管理界面，确保Porter用户看到的是自助配置界面：

```php
// 在 class-chiral-hub-roles.php 中修改权限重定向
public function redirect_porter_to_self_config( $redirect_to, $request, $user ) {
    if ( isset( $user->roles ) && in_array( Chiral_Hub_Roles::ROLE_SLUG, $user->roles ) ) {
        // Porter用户登录后直接跳转到自己的配置页面
        return admin_url( 'admin.php?page=chiral-porter-config' );
    }
    return $redirect_to;
}

// 隐藏不必要的WordPress后台菜单
public function hide_admin_menus_for_porter() {
    if ( ! current_user_can( Chiral_Hub_Roles::ROLE_SLUG ) ) {
        return;
    }
    
    // 隐藏不需要的菜单项
    remove_menu_page( 'index.php' );          // 仪表盘
    remove_menu_page( 'edit.php' );           // 文章
    remove_menu_page( 'upload.php' );         // 媒体库
    remove_menu_page( 'edit.php?post_type=page' ); // 页面
    remove_menu_page( 'edit-comments.php' );  // 评论
    remove_menu_page( 'themes.php' );         // 外观
    remove_menu_page( 'plugins.php' );        // 插件
    remove_menu_page( 'users.php' );          // 用户
    remove_menu_page( 'tools.php' );          // 工具
    remove_menu_page( 'options-general.php' ); // 设置
    
    // 只保留Porter需要的菜单
    // My Configuration (由我们的插件添加)
    // Profile (个人资料，用于管理Application Passwords)
}
```

---

## 🔄 **定时任务系统**

### 1. WordPress Cron扩展

在现有节点检查基础上增加RSS处理任务：

```php
// 在activator中注册新的cron事件
wp_schedule_event( time(), 'hourly', 'chiral_hub_rss_sync_hourly' );
wp_schedule_event( time(), 'daily', 'chiral_hub_rss_patrol_daily' );

// 添加自定义cron间隔
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['every_30_minutes'] = array(
        'interval' => 30 * MINUTE_IN_SECONDS,
        'display'  => __( 'Every 30 Minutes', 'chiral-hub-core' )
    );
    return $schedules;
} );
```

### 2. 任务处理器

```php
class Chiral_Hub_RSS_Scheduler {
    
    public function handle_hourly_rss_sync() {
        $rss_porters = $this->get_rss_porters_for_sync();
        
        foreach ( $rss_porters as $porter ) {
            $this->queue_rss_sync_task( $porter->ID );
        }
    }
    
    public function handle_daily_content_patrol() {
        $rss_porters = $this->get_all_rss_porters();
        
        foreach ( $rss_porters as $porter ) {
            $this->queue_patrol_task( $porter->ID );
        }
    }
    
    private function get_rss_porters_for_sync() {
        return get_users( array(
            'role' => Chiral_Hub_Roles::ROLE_SLUG,
            'meta_query' => array(
                array(
                    'key' => '_chiral_sync_mode',
                    'value' => 'rss',
                    'compare' => '='
                )
            )
        ) );
    }
}
```

---

## 🎨 **用户界面设计**

### 1. Porter模式切换界面

```html
<!-- Porter编辑模态窗口 -->
<div id="porter-config-modal" class="chiral-modal">
    <div class="modal-content">
        <h2><?php _e( 'Configure Porter Node', 'chiral-hub-core' ); ?></h2>
        
        <div class="sync-mode-selector">
            <h3><?php _e( 'Synchronization Mode', 'chiral-hub-core' ); ?></h3>
            <label class="mode-option">
                <input type="radio" name="sync_mode" value="wordpress" checked>
                <div class="mode-card wordpress-mode">
                    <h4>⚡ WordPress Mode</h4>
                    <p><?php _e( 'Real-time sync using Connector plugin', 'chiral-hub-core' ); ?></p>
                </div>
            </label>
            
            <label class="mode-option">
                <input type="radio" name="sync_mode" value="rss">
                <div class="mode-card rss-mode">
                    <h4>📄 RSS Mode</h4>
                    <p><?php _e( 'Periodic sync via RSS feeds and Sitemaps', 'chiral-hub-core' ); ?></p>
                </div>
            </label>
        </div>
        
        <!-- WordPress模式配置（现有内容） -->
        <div id="wordpress-config" class="mode-config">
            <h4><?php _e( 'WordPress Configuration', 'chiral-hub-core' ); ?></h4>
            <p><?php _e( 'Install Chiral Connector plugin on your WordPress site and configure the connection.', 'chiral-hub-core' ); ?></p>
        </div>
        
        <!-- RSS模式配置 -->
        <div id="rss-config" class="mode-config" style="display: none;">
            <h4><?php _e( 'RSS Configuration', 'chiral-hub-core' ); ?></h4>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'RSS Feed URL', 'chiral-hub-core' ); ?></th>
                    <td>
                        <input type="url" name="rss_url" class="regular-text" placeholder="https://example.com/feed/">
                        <p class="description"><?php _e( 'Your blog\'s RSS/Atom feed URL', 'chiral-hub-core' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Sitemap URL', 'chiral-hub-core' ); ?></th>
                    <td>
                        <input type="url" name="sitemap_url" class="regular-text" placeholder="https://example.com/sitemap.xml">
                        <p class="description"><?php _e( 'For initial bulk import of all posts', 'chiral-hub-core' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Sync Frequency', 'chiral-hub-core' ); ?></th>
                    <td>
                        <select name="sync_frequency">
                            <option value="hourly"><?php _e( 'Every Hour', 'chiral-hub-core' ); ?></option>
                            <option value="daily"><?php _e( 'Daily', 'chiral-hub-core' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <div class="rss-actions">
                <button type="button" class="button" id="test-rss-connection">
                    <?php _e( 'Test RSS Connection', 'chiral-hub-core' ); ?>
                </button>
                <button type="button" class="button button-primary" id="start-sitemap-import">
                    <?php _e( 'Start Sitemap Import', 'chiral-hub-core' ); ?>
                </button>
            </div>
        </div>
        
        <div class="modal-actions">
            <button type="button" class="button button-primary save-porter-config">
                <?php _e( 'Save Configuration', 'chiral-hub-core' ); ?>
            </button>
            <button type="button" class="button close-modal">
                <?php _e( 'Cancel', 'chiral-hub-core' ); ?>
            </button>
        </div>
    </div>
</div>
```

### 2. 批量导入进度界面

```html
<!-- Sitemap导入进度界面 -->
<div id="sitemap-import-progress" class="chiral-progress-section">
    <h3><?php _e( 'Sitemap Import Progress', 'chiral-hub-core' ); ?></h3>
    
    <div class="progress-container">
        <div class="progress-bar">
            <div class="progress-fill" style="width: 0%;"></div>
        </div>
        <span class="progress-text">0% (0/0)</span>
    </div>
    
    <div class="import-stats">
        <div class="stat-item">
            <span class="stat-label"><?php _e( 'Total URLs', 'chiral-hub-core' ); ?>:</span>
            <span class="stat-value total-urls">-</span>
        </div>
        <div class="stat-item">
            <span class="stat-label"><?php _e( 'Processed', 'chiral-hub-core' ); ?>:</span>
            <span class="stat-value processed">-</span>
        </div>
        <div class="stat-item">
            <span class="stat-label"><?php _e( 'Success', 'chiral-hub-core' ); ?>:</span>
            <span class="stat-value success">-</span>
        </div>
        <div class="stat-item">
            <span class="stat-label"><?php _e( 'Failed', 'chiral-hub-core' ); ?>:</span>
            <span class="stat-value failed">-</span>
        </div>
        <div class="stat-item">
            <span class="stat-label"><?php _e( 'ETA', 'chiral-hub-core' ); ?>:</span>
            <span class="stat-value eta">-</span>
        </div>
    </div>
    
    <div class="current-processing">
        <strong><?php _e( 'Current', 'chiral-hub-core' ); ?>:</strong>
        <span class="current-url">-</span>
    </div>
    
    <div class="import-log">
        <h4><?php _e( 'Import Log', 'chiral-hub-core' ); ?></h4>
        <div class="log-container" style="height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
            <!-- 日志内容将通过JavaScript动态添加 -->
        </div>
    </div>
</div>
```

---

## 🚀 **API扩展设计**

### 1. 新增RSS管理API端点

扩展 `class-chiral-hub-rest-api.php`：

```php
// 新增API端点注册
register_rest_route( self::API_NAMESPACE, '/rss/import-sitemap', array(
    'methods'  => WP_REST_Server::CREATABLE,
    'callback' => array( $this, 'start_sitemap_import' ),
    'permission_callback' => array( $this, 'can_manage_rss' ),
    'args' => array(
        'porter_id' => array(
            'required' => true,
            'type' => 'integer',
            'validate_callback' => function( $param ) {
                return is_numeric( $param ) && $param > 0;
            }
        ),
        'sitemap_url' => array(
            'required' => true,
            'type' => 'string',
            'format' => 'uri',
            'validate_callback' => function( $param ) {
                return filter_var( $param, FILTER_VALIDATE_URL ) !== false;
            }
        )
    )
) );

register_rest_route( self::API_NAMESPACE, '/rss/import-status', array(
    'methods'  => WP_REST_Server::READABLE,
    'callback' => array( $this, 'get_import_status' ),
    'permission_callback' => array( $this, 'can_manage_rss' ),
    'args' => array(
        'porter_id' => array(
            'required' => true,
            'type' => 'integer'
        )
    )
) );

register_rest_route( self::API_NAMESPACE, '/rss/sync-now', array(
    'methods'  => WP_REST_Server::CREATABLE,
    'callback' => array( $this, 'trigger_manual_sync' ),
    'permission_callback' => array( $this, 'can_manage_rss' ),
    'args' => array(
        'porter_id' => array(
            'required' => true,
            'type' => 'integer'
        ),
        'urls' => array(
            'required' => false,
            'type' => 'array',
            'description' => 'Specific URLs to sync (if empty, sync all from RSS)'
        )
    )
) );
```

### 2. API响应处理器

```php
/**
 * 开始Sitemap导入
 */
public function start_sitemap_import( WP_REST_Request $request ) {
    $porter_id = $request->get_param( 'porter_id' );
    $sitemap_url = $request->get_param( 'sitemap_url' );
    
    // 验证Porter用户和RSS模式
    $porter = get_user_by( 'ID', $porter_id );
    if ( ! $porter || ! in_array( Chiral_Hub_Roles::ROLE_SLUG, $porter->roles ) ) {
        return new WP_Error( 'invalid_porter', __( 'Invalid Porter user.', 'chiral-hub-core' ), array( 'status' => 400 ) );
    }
    
    $sync_mode = get_user_meta( $porter_id, '_chiral_sync_mode', true );
    if ( $sync_mode !== 'rss' ) {
        return new WP_Error( 'invalid_mode', __( 'Porter is not in RSS mode.', 'chiral-hub-core' ), array( 'status' => 400 ) );
    }
    
    // 检查是否已有导入任务在进行
    $existing_import = get_user_meta( $porter_id, '_chiral_import_in_progress', true );
    if ( $existing_import ) {
        return new WP_Error( 'import_in_progress', __( 'An import is already in progress for this Porter.', 'chiral-hub-core' ), array( 'status' => 409 ) );
    }
    
    // 启动异步导入任务
    $crawler = new Chiral_Hub_RSS_Crawler( $this->plugin_name, CHIRAL_HUB_CORE_VERSION );
    $result = $crawler->initiate_sitemap_import( $porter_id, $sitemap_url );
    
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    
    return new WP_REST_Response( array(
        'success' => true,
        'message' => __( 'Sitemap import started successfully.', 'chiral-hub-core' ),
        'import_id' => $result['import_id'],
        'estimated_items' => $result['estimated_items']
    ), 202 );
}

/**
 * 获取导入状态
 */
public function get_import_status( WP_REST_Request $request ) {
    $porter_id = $request->get_param( 'porter_id' );
    
    $import_status = get_user_meta( $porter_id, '_chiral_import_status', true );
    $queue_status = ( new Chiral_Hub_RSS_Queue() )->get_porter_queue_status( $porter_id );
    
    return new WP_REST_Response( array(
        'is_importing' => ! empty( $import_status ),
        'progress' => $import_status['progress'] ?? 0,
        'total_items' => $import_status['total_items'] ?? 0,
        'processed_items' => $import_status['processed_items'] ?? 0,
        'success_count' => $import_status['success_count'] ?? 0,
        'error_count' => $import_status['error_count'] ?? 0,
        'current_url' => $import_status['current_url'] ?? '',
        'eta_minutes' => $import_status['eta_minutes'] ?? null,
        'queue_pending' => $queue_status['pending'] ?? 0,
        'queue_processing' => $queue_status['processing'] ?? 0,
        'last_error' => $import_status['last_error'] ?? null
    ), 200 );
}
```

---

## 🔍 **静态JS客户端保持兼容**

现有的静态JS客户端无需任何修改，因为：

1. **API端点不变**：继续使用 `/chiral-network/v1/related-data`
2. **响应格式不变**：返回的JSON结构保持一致
3. **配置方式不变**：仍然使用`nodeId`进行标识

```javascript
// 现有客户端代码完全兼容
const client = new ChiralStaticClient({
    hubUrl: 'https://hub.example.com',
    nodeId: 'blog-001' // 无论是WordPress还是RSS模式的nodeId
});

// API调用保持不变
client.getRelatedPosts(currentUrl, count).then(posts => {
    // 处理相关文章，无论来源是WordPress还是RSS
});
```

---

## 📅 **开发阶段规划**

### 阶段一：核心基础设施 (2周)

**第1周：数据层扩展**
- [ ] 扩展用户元数据结构
- [ ] 扩展chiral_data CPT元数据
- [ ] 创建RSS队列数据表
- [ ] 更新数据库迁移脚本

**第2周：核心类开发**
- [ ] 创建 `Chiral_Hub_RSS_Crawler` 类
- [ ] 创建 `Chiral_Hub_RSS_Queue` 类
- [ ] 实现基本的RSS解析和Sitemap解析功能
- [ ] 创建内容抓取和元数据提取功能

### 阶段二：管理界面开发 (2周)

**第3周：后台界面扩展**
- [ ] 扩展管理员设置页面
- [ ] 更新节点管理页面显示RSS模式
- [ ] 创建Porter配置模态窗口
- [ ] 实现模式切换功能

**第4周：进度监控界面**
- [ ] 创建Sitemap导入进度界面
- [ ] 实现实时进度更新
- [ ] 添加导入日志显示
- [ ] 创建手动同步控制面板

### 阶段三：API和任务系统 (2周)

**第5周：REST API扩展**
- [ ] 添加RSS管理API端点
- [ ] 实现Sitemap导入API
- [ ] 添加同步状态查询API
- [ ] 创建手动同步触发API

**第6周：定时任务系统**
- [ ] 扩展WordPress Cron系统
- [ ] 实现RSS增量同步任务
- [ ] 创建智能内容巡检任务
- [ ] 添加任务队列处理器

### 阶段四：测试和优化 (2周)

**第7周：功能测试**
- [ ] 端到端功能测试
- [ ] RSS解析兼容性测试
- [ ] 大量数据导入测试
- [ ] 错误处理和重试机制测试

**第8周：性能优化和文档**
- [ ] 性能优化和内存使用优化
- [ ] 创建用户使用文档
- [ ] 编写开发者API文档
- [ ] 准备发布版本

---

## 🔒 **安全考虑**

### 1. 输入验证

```php
// RSS URL验证
private function validate_rss_url( $url ) {
    if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        return false;
    }
    
    $parsed = parse_url( $url );
    if ( ! in_array( $parsed['scheme'], array( 'http', 'https' ) ) ) {
        return false;
    }
    
    // 防止内网地址访问
    $ip = gethostbyname( $parsed['host'] );
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
        return false;
    }
    
    return true;
}
```

### 2. 内容安全

```php
// HTML内容清理
private function sanitize_crawled_content( $content ) {
    // 移除脚本和危险标签
    $content = wp_kses( $content, array(
        'p' => array(),
        'br' => array(),
        'strong' => array(),
        'em' => array(),
        'a' => array( 'href' => array() ),
        'img' => array( 'src' => array(), 'alt' => array() )
    ) );
    
    // 限制内容长度
    if ( strlen( $content ) > 50000 ) { // 50KB limit
        $content = substr( $content, 0, 50000 ) . '...';
    }
    
    return $content;
}
```

### 3. 速率限制

```php
// 防止过于频繁的抓取
private function check_rate_limit( $porter_id ) {
    $last_request = get_user_meta( $porter_id, '_chiral_last_crawl_request', true );
    $min_interval = 30; // 30秒最小间隔
    
    if ( $last_request && ( time() - $last_request ) < $min_interval ) {
        return false;
    }
    
    update_user_meta( $porter_id, '_chiral_last_crawl_request', time() );
    return true;
}
```

---

## 📊 **监控和分析**

### 1. 同步状态监控

```php
// 创建同步状态仪表板
class Chiral_Hub_RSS_Dashboard {
    
    public function render_dashboard_widget() {
        $rss_stats = $this->get_rss_sync_statistics();
        ?>
        <div class="chiral-rss-dashboard">
            <h3><?php _e( 'RSS Mode Statistics', 'chiral-hub-core' ); ?></h3>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <h4><?php _e( 'RSS Nodes', 'chiral-hub-core' ); ?></h4>
                    <span class="stat-number"><?php echo $rss_stats['total_rss_nodes']; ?></span>
                </div>
                
                <div class="stat-card">
                    <h4><?php _e( 'Last 24h Syncs', 'chiral-hub-core' ); ?></h4>
                    <span class="stat-number"><?php echo $rss_stats['syncs_24h']; ?></span>
                </div>
                
                <div class="stat-card">
                    <h4><?php _e( 'Queue Pending', 'chiral-hub-core' ); ?></h4>
                    <span class="stat-number"><?php echo $rss_stats['queue_pending']; ?></span>
                </div>
                
                <div class="stat-card">
                    <h4><?php _e( 'Success Rate', 'chiral-hub-core' ); ?></h4>
                    <span class="stat-number"><?php echo $rss_stats['success_rate']; ?>%</span>
                </div>
            </div>
        </div>
        <?php
    }
}
```

### 2. 错误日志和报告

```php
// RSS同步错误收集
private function log_rss_error( $porter_id, $error_type, $error_message, $context = array() ) {
    $error_data = array(
        'timestamp' => current_time( 'mysql' ),
        'porter_id' => $porter_id,
        'error_type' => $error_type,
        'message' => $error_message,
        'context' => $context
    );
    
    // 保存到用户元数据
    $errors = get_user_meta( $porter_id, '_chiral_rss_sync_errors', true );
    if ( ! is_array( $errors ) ) {
        $errors = array();
    }
    
    array_unshift( $errors, $error_data );
    
    // 只保留最近50条错误
    $errors = array_slice( $errors, 0, 50 );
    
    update_user_meta( $porter_id, '_chiral_rss_sync_errors', $errors );
    
    // 同时写入WordPress错误日志
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[Chiral RSS] ' . $error_type . ' for Porter ' . $porter_id . ': ' . $error_message );
    }
}
```

---

## 🎯 **成功指标**

### 技术指标

1. **同步准确性**：RSS模式的同步成功率 ≥ 95%
2. **性能表现**：单个页面抓取平均耗时 ≤ 3秒
3. **系统稳定性**：24小时内系统可用性 ≥ 99%
4. **资源使用**：RSS抓取任务内存使用 ≤ 64MB

### 业务指标

1. **用户体验**：RSS模式Porter配置完成率 ≥ 80%
2. **数据质量**：抓取的文章元数据完整性 ≥ 90%
3. **相关性质量**：RSS模式相关文章推荐质量与WordPress模式相当
4. **管理效率**：Hub管理员可在统一界面管理两种模式

---

## 🚀 **部署和发布计划**

### 1. 测试环境验证

```bash
# 自动化测试脚本示例
#!/bin/bash

echo "开始RSS模式功能测试..."

# 测试RSS解析
php wp-cli.phar eval "
\$crawler = new Chiral_Hub_RSS_Crawler('chiral-hub-core', '1.1.0');
\$result = \$crawler->test_rss_parsing('https://example.com/feed');
echo \$result ? 'RSS解析测试通过' : 'RSS解析测试失败';
"

# 测试Sitemap解析
php wp-cli.phar eval "
\$crawler = new Chiral_Hub_RSS_Crawler('chiral-hub-core', '1.1.0');
\$result = \$crawler->test_sitemap_parsing('https://example.com/sitemap.xml');
echo \$result ? 'Sitemap解析测试通过' : 'Sitemap解析测试失败';
"

echo "功能测试完成"
```

### 2. 渐进式发布

**Phase 1**: 内部测试环境
- 完整功能测试
- 性能基准测试
- 安全漏洞扫描

**Phase 2**: Beta测试
- 邀请5-10个静态博客用户测试
- 收集用户反馈
- 修复发现的问题

**Phase 3**: 正式发布
- 更新版本号到1.2.0
- 发布完整功能文档
- 监控生产环境表现

### 3. 数据库迁移

```php
// 版本1.2.0数据库更新
function chiral_hub_core_update_120() {
    global $wpdb;
    
    // 创建RSS队列表
    $table_name = $wpdb->prefix . 'chiral_rss_queue';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        porter_user_id bigint(20) UNSIGNED NOT NULL,
        action_type varchar(20) NOT NULL,
        target_url text NOT NULL,
        priority tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
        status varchar(20) NOT NULL DEFAULT 'pending',
        attempts tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
        scheduled_time datetime NOT NULL,
        completed_time datetime NULL,
        error_message text NULL,
        metadata longtext NULL,
        PRIMARY KEY (id),
        KEY porter_status (porter_user_id, status),
        KEY scheduled_priority (scheduled_time, priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    // 为现有Porter用户设置默认同步模式
    $porters = get_users( array( 'role' => Chiral_Hub_Roles::ROLE_SLUG ) );
    foreach ( $porters as $porter ) {
        $existing_mode = get_user_meta( $porter->ID, '_chiral_sync_mode', true );
        if ( empty( $existing_mode ) ) {
            update_user_meta( $porter->ID, '_chiral_sync_mode', 'wordpress' );
        }
    }
    
    // 更新版本号
    update_option( 'chiral_hub_core_db_version', '1.2.0' );
}
```

---

## 📚 **用户文档计划**

### 1. 管理员指南

```markdown
# Chiral Hub RSS模式使用指南

## 启用RSS模式支持

1. 登录Hub管理后台
2. 进入"Chiral Hub" → "Settings"
3. 勾选"Enable RSS Mode Support"
4. 配置默认RSS同步频率
5. 保存设置

## 添加RSS模式节点

1. 进入"Chiral Hub" → "Node Management"
2. 点击"Add New Porter"或编辑现有Porter
3. 选择"RSS Mode"
4. 填写RSS Feed URL和Sitemap URL
5. 点击"Start Sitemap Import"开始批量导入
```

### 2. 静态博客用户指南

```markdown
# 静态博客接入Chiral网络指南

## 前置要求

- 你的博客需要提供RSS/Atom feed
- 建议有sitemap.xml文件
- 博客页面需要包含基本的meta标签

## 接入步骤

1. 联系Hub管理员申请账号
2. 提供你的RSS feed URL
3. 等待管理员配置完成
4. 在你的博客中集成Chiral静态客户端
5. 享受相关文章推荐功能
```

---

## 🎉 **总结**

此RSS双模式支持方案通过以下关键设计实现了目标：

1. **完全兼容性**：现有WordPress用户体验零影响
2. **统一数据模型**：两种模式的数据最终都转换为统一的chiral_data格式
3. **灵活的任务系统**：基于WordPress Cron的可扩展异步处理
4. **友好的管理界面**：直观的配置和监控界面
5. **强大的API支持**：为静态客户端提供一致的API体验

通过8周的分阶段开发，我们将为Chiral Hub生态系统带来对静态博客的完整支持，显著扩大潜在用户群体，同时保持系统的稳定性和可维护性。 