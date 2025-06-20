<?php

/**
 * RSS爬虫和数据处理器
 *
 * @link       https://example.com
 * @since      1.2.0
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 */

/**
 * RSS爬虫和数据处理器类
 *
 * 负责处理RSS feeds、Sitemap解析、内容抓取和元数据提取
 *
 * @package    Chiral_Hub_Core
 * @subpackage Chiral_Hub_Core/includes
 * @author     Your Name <email@example.com>
 */
class Chiral_Hub_RSS_Crawler {

    /**
     * The ID of this plugin.
     *
     * @since    1.2.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.2.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.2.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * 测试RSS和Sitemap连接
     *
     * @since 1.2.0
     * @param string $rss_url RSS feed URL
     * @param string $sitemap_url Sitemap URL
     * @return array|WP_Error 测试结果
     */
    public function test_connection( $rss_url, $sitemap_url ) {
        $result = array(
            'rss_items' => 0,
            'sitemap_urls' => 0
        );

        // 测试RSS连接
        if ( !empty( $rss_url ) ) {
            $rss_response = wp_remote_get( $rss_url, array(
                'timeout' => 30,
                'user-agent' => 'Chiral Hub RSS Crawler/' . $this->version
            ) );

            if ( is_wp_error( $rss_response ) ) {
                return new WP_Error( 'rss_connection_failed', 
                    sprintf( __( 'Failed to connect to RSS feed: %s', 'chiral-hub-core' ), $rss_response->get_error_message() ) );
            }

            $rss_body = wp_remote_retrieve_body( $rss_response );
            $rss_data = $this->parse_rss_feed( $rss_body );
            
            if ( is_wp_error( $rss_data ) ) {
                return $rss_data;
            }

            $result['rss_items'] = count( $rss_data );
        }

        // 测试Sitemap连接
        if ( !empty( $sitemap_url ) ) {
            $sitemap_response = wp_remote_get( $sitemap_url, array(
                'timeout' => 30,
                'user-agent' => 'Chiral Hub RSS Crawler/' . $this->version
            ) );

            if ( is_wp_error( $sitemap_response ) ) {
                return new WP_Error( 'sitemap_connection_failed', 
                    sprintf( __( 'Failed to connect to Sitemap: %s', 'chiral-hub-core' ), $sitemap_response->get_error_message() ) );
            }

            $sitemap_body = wp_remote_retrieve_body( $sitemap_response );
            $sitemap_urls = $this->parse_sitemap( $sitemap_body, 0 );
            
            if ( is_wp_error( $sitemap_urls ) ) {
                return $sitemap_urls;
            }

            $result['sitemap_urls'] = count( $sitemap_urls );
        }

        return $result;
    }

    /**
     * 启动Sitemap批量导入
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     * @param string $sitemap_url Sitemap URL
     * @return array|WP_Error 导入结果
     */
    public function initiate_sitemap_import( $porter_user_id, $sitemap_url ) {
        // 检查是否已有导入在进行
        $existing_import = get_user_meta( $porter_user_id, '_chiral_import_in_progress', true );
        if ( $existing_import ) {
            return new WP_Error( 'import_in_progress', __( 'An import is already in progress for this Porter.', 'chiral-hub-core' ) );
        }

        // 解析Sitemap获取URL列表
        $sitemap_response = wp_remote_get( $sitemap_url, array(
            'timeout' => 30,
            'user-agent' => 'Chiral Hub RSS Crawler/' . $this->version
        ) );

        if ( is_wp_error( $sitemap_response ) ) {
            return new WP_Error( 'sitemap_fetch_failed', 
                sprintf( __( 'Failed to fetch Sitemap: %s', 'chiral-hub-core' ), $sitemap_response->get_error_message() ) );
        }

        $sitemap_body = wp_remote_retrieve_body( $sitemap_response );
        $urls = $this->parse_sitemap( $sitemap_body, $porter_user_id );

        if ( is_wp_error( $urls ) ) {
            return $urls;
        }

        if ( empty( $urls ) ) {
            return new WP_Error( 'no_urls_found', __( 'No URLs found in Sitemap.', 'chiral-hub-core' ) );
        }

        // 设置导入状态
        $import_id = uniqid( 'import_' );
        $import_status = array(
            'import_id' => $import_id,
            'total_items' => count( $urls ),
            'processed_items' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'progress' => 0,
            'current_url' => '',
            'started_at' => time(),
            'last_update' => time()
        );

        update_user_meta( $porter_user_id, '_chiral_import_status', $import_status );
        update_user_meta( $porter_user_id, '_chiral_import_in_progress', true );

        // 将URL添加到队列
        if ( class_exists( 'Chiral_Hub_RSS_Queue' ) ) {
            $queue = new Chiral_Hub_RSS_Queue();
            foreach ( $urls as $url ) {
                $queue->enqueue_task( $porter_user_id, 'initial_import', $url, 5 );
            }
        } else {
            // 如果队列类不存在，使用简单的批量处理
            // 将URLs保存到用户元数据中，以便处理函数可以访问
            update_user_meta( $porter_user_id, '_chiral_import_urls', $urls );
            wp_schedule_single_event( time() + 5, 'chiral_hub_process_sitemap_import', array( $porter_user_id, $urls ) );
        }

        return array(
            'import_id' => $import_id,
            'total_urls' => count( $urls )
        );
    }

    /**
     * 处理RSS增量更新
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     * @return array 更新结果
     */
    public function process_rss_updates( $porter_user_id ) {
        $rss_url = get_user_meta( $porter_user_id, '_chiral_rss_url', true );
        
        if ( empty( $rss_url ) ) {
            return array( 'error' => __( 'No RSS URL configured for this Porter.', 'chiral-hub-core' ) );
        }

        // 获取RSS内容
        $rss_response = wp_remote_get( $rss_url, array(
            'timeout' => 30,
            'user-agent' => 'Chiral Hub RSS Crawler/' . $this->version
        ) );

        if ( is_wp_error( $rss_response ) ) {
            $this->log_rss_error( $porter_user_id, 'rss_fetch_failed', $rss_response->get_error_message() );
            return array( 'error' => $rss_response->get_error_message() );
        }

        $rss_body = wp_remote_retrieve_body( $rss_response );
        $rss_items = $this->parse_rss_feed( $rss_body );

        if ( is_wp_error( $rss_items ) ) {
            $this->log_rss_error( $porter_user_id, 'rss_parse_failed', $rss_items->get_error_message() );
            return array( 'error' => $rss_items->get_error_message() );
        }

        $results = array(
            'total' => count( $rss_items ),
            'new' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        );

        // 处理每个RSS条目
        foreach ( $rss_items as $item ) {
            $result = $this->process_rss_item( $porter_user_id, $item );
            if ( isset( $result['action'] ) ) {
                $results[ $result['action'] ]++;
            } else {
                $results['errors']++;
            }
        }

        // 更新最后同步时间
        update_user_meta( $porter_user_id, '_chiral_rss_last_sync', time() );

        return array(
            'success' => true,
            'new_items' => $results['new'],
            'updated_items' => $results['updated'],
            'total_processed' => $results['total'],
            'skipped' => $results['skipped'],
            'errors' => $results['errors']
        );
    }

    /**
     * 解析RSS feed
     *
     * @since 1.2.0
     * @param string $rss_content RSS内容
     * @return array|WP_Error RSS条目数组
     */
    private function parse_rss_feed( $rss_content ) {
        if ( empty( $rss_content ) ) {
            return new WP_Error( 'empty_rss', __( 'RSS feed content is empty.', 'chiral-hub-core' ) );
        }

        // 禁用libxml错误，避免警告
        $use_errors = libxml_use_internal_errors( true );
        
        $xml = simplexml_load_string( $rss_content );
        
        // 恢复libxml错误设置
        libxml_use_internal_errors( $use_errors );

        if ( $xml === false ) {
            return new WP_Error( 'invalid_xml', __( 'Invalid RSS XML format.', 'chiral-hub-core' ) );
        }

        $items = array();

        // 支持RSS 2.0和Atom feeds
        if ( isset( $xml->channel->item ) ) {
            // RSS 2.0
            foreach ( $xml->channel->item as $item ) {
                $featured_image = $this->extract_featured_image_from_rss_item( $item );
                
                $items[] = array(
                    'title' => (string) $item->title,
                    'link' => (string) $item->link,
                    'description' => (string) $item->description,
                    'pubDate' => (string) $item->pubDate,
                    'guid' => (string) $item->guid,
                    'content' => isset( $item->children( 'http://purl.org/rss/1.0/modules/content/' )->encoded ) 
                        ? (string) $item->children( 'http://purl.org/rss/1.0/modules/content/' )->encoded
                        : (string) $item->description,
                    'featured_image' => $featured_image
                );
            }
        } elseif ( isset( $xml->entry ) ) {
            // Atom
            foreach ( $xml->entry as $entry ) {
                $link = '';
                if ( isset( $entry->link ) ) {
                    $link = isset( $entry->link['href'] ) ? (string) $entry->link['href'] : (string) $entry->link;
                }
                
                $featured_image = $this->extract_featured_image_from_atom_entry( $entry );
                
                $items[] = array(
                    'title' => (string) $entry->title,
                    'link' => $link,
                    'description' => (string) $entry->summary,
                    'pubDate' => (string) $entry->published,
                    'guid' => (string) $entry->id,
                    'content' => isset( $entry->content ) ? (string) $entry->content : (string) $entry->summary,
                    'featured_image' => $featured_image
                );
            }
        }

        return $items;
    }

    /**
     * 解析Sitemap获取URL列表
     *
     * @since 1.2.0
     * @param string $sitemap_content Sitemap内容
     * @param int $porter_user_id Porter用户ID
     * @return array|WP_Error URL数组
     */
    private function parse_sitemap( $sitemap_content, $porter_user_id = 0 ) {
        if ( empty( $sitemap_content ) ) {
            return new WP_Error( 'empty_sitemap', __( 'Sitemap content is empty.', 'chiral-hub-core' ) );
        }

        $use_errors = libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $sitemap_content );
        libxml_use_internal_errors( $use_errors );

        if ( $xml === false ) {
            return new WP_Error( 'invalid_sitemap_xml', __( 'Invalid Sitemap XML format.', 'chiral-hub-core' ) );
        }

        $urls = array();

        // 检查是否是sitemap index
        if ( isset( $xml->sitemap ) ) {
            // 这是sitemap index，递归解析子sitemap
            foreach ( $xml->sitemap as $sitemap ) {
                $sitemap_url = (string) $sitemap->loc;
                $sub_response = wp_remote_get( $sitemap_url, array(
                    'timeout' => 30,
                    'user-agent' => 'Chiral Hub RSS Crawler/' . $this->version
                ) );

                if ( !is_wp_error( $sub_response ) ) {
                    $sub_content = wp_remote_retrieve_body( $sub_response );
                    $sub_urls = $this->parse_sitemap( $sub_content, $porter_user_id );
                    if ( !is_wp_error( $sub_urls ) ) {
                        $urls = array_merge( $urls, $sub_urls );
                    }
                }
            }
        } elseif ( isset( $xml->url ) ) {
            // 标准sitemap
            foreach ( $xml->url as $url ) {
                $urls[] = (string) $url->loc;
            }
        }

        // 过滤非文章类URL
        $urls = $this->filter_article_urls( $urls, $porter_user_id );

        return $urls;
    }

    /**
     * 处理单个RSS条目
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     * @param array $rss_item RSS条目数据
     * @return array 处理结果
     */
    private function process_rss_item( $porter_user_id, $rss_item ) {
        $node_id = get_user_meta( $porter_user_id, '_chiral_node_id', true );
        
        if ( empty( $node_id ) ) {
            return array( 'error' => 'No node ID for Porter' );
        }

        // 检查是否已存在此RSS条目
        $existing_post = $this->find_existing_rss_item( $node_id, $rss_item['guid'], $rss_item['link'] );

        if ( $existing_post ) {
            // 检查是否需要更新
            $content_hash = md5( $rss_item['content'] . $rss_item['title'] );
            $existing_hash = get_post_meta( $existing_post->ID, '_chiral_content_hash', true );

            if ( $content_hash !== $existing_hash ) {
                // 更新现有文章
                $this->update_chiral_data_from_rss( $existing_post->ID, $rss_item, $content_hash );
                return array( 'action' => 'updated' );
            } else {
                return array( 'action' => 'skipped' );
            }
        } else {
            // 创建新文章
            $post_id = $this->create_chiral_data_from_rss( $porter_user_id, $rss_item );
            if ( $post_id ) {
                return array( 'action' => 'new' );
            } else {
                return array( 'error' => 'Failed to create post' );
            }
        }
    }

    /**
     * 查找现有的RSS条目
     *
     * @since 1.2.0
     * @param string $node_id 节点ID
     * @param string $guid RSS GUID
     * @param string $link 文章链接
     * @return WP_Post|null 现有文章或null
     */
    private function find_existing_rss_item( $node_id, $guid, $link ) {
        $args = array(
            'post_type' => Chiral_Hub_CPT::CPT_SLUG,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_chiral_node_id',
                    'value' => $node_id,
                    'compare' => '='
                ),
                array(
                    'relation' => 'OR',
                    array(
                        'key' => '_chiral_rss_entry_guid',
                        'value' => $guid,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'chiral_source_url',
                        'value' => $link,
                        'compare' => '='
                    )
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'any'
        );

        $posts = get_posts( $args );
        return !empty( $posts ) ? $posts[0] : null;
    }

    /**
     * 从RSS创建chiral_data文章
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     * @param array $rss_item RSS条目数据
     * @return int|false 文章ID或false
     */
    private function create_chiral_data_from_rss( $porter_user_id, $rss_item ) {
        $node_id = get_user_meta( $porter_user_id, '_chiral_node_id', true );
        
        // 解析发布日期
        $publish_date = $this->parse_rss_date( $rss_item['pubDate'] );
        
        // 生成内容哈希
        $content_hash = md5( $rss_item['content'] . $rss_item['title'] );

        // 准备文章数据
        $post_data = array(
            'post_title' => sanitize_text_field( $rss_item['title'] ),
            'post_content' => wp_kses_post( $rss_item['content'] ),
            'post_excerpt' => wp_trim_words( strip_tags( $rss_item['description'] ), 55 ),
            'post_status' => $this->get_default_post_status( $porter_user_id ),
            'post_author' => $porter_user_id,
            'post_type' => Chiral_Hub_CPT::CPT_SLUG,
            'post_date' => $publish_date,
            'post_date_gmt' => get_gmt_from_date( $publish_date ),
            'meta_input' => array(
                'chiral_source_url' => esc_url_raw( $rss_item['link'] ),
                '_chiral_node_id' => sanitize_text_field( $node_id ),
                '_chiral_source_type' => 'rss',
                '_chiral_sync_method' => 'rss_crawl',
                '_chiral_rss_entry_guid' => sanitize_text_field( $rss_item['guid'] ),
                '_chiral_content_hash' => $content_hash,
                '_chiral_last_crawl_check' => time(),
                '_chiral_data_original_publish_date' => $publish_date,
                'other_URLs' => wp_json_encode( array( 'source' => $rss_item['link'] ) )
            )
        );

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            $this->log_rss_error( $porter_user_id, 'post_creation_failed', $post_id->get_error_message(), array( 'rss_item' => $rss_item ) );
            return false;
        }

        // 处理头图
        if ( !empty( $rss_item['featured_image'] ) ) {
            error_log( 'Chiral Hub RSS: Processing featured image for post ' . $post_id . ': ' . $rss_item['featured_image'] );
            $result = $this->set_featured_image_from_url( $post_id, $rss_item['featured_image'] );
            if ( is_wp_error( $result ) ) {
                error_log( 'Chiral Hub RSS: Failed to set featured image: ' . $result->get_error_message() );
            } else {
                error_log( 'Chiral Hub RSS: Successfully set featured image with attachment ID: ' . $result );
            }
        } else {
            error_log( 'Chiral Hub RSS: No featured image found in RSS item for post ' . $post_id );
        }

        return $post_id;
    }

    /**
     * 更新现有的chiral_data文章
     *
     * @since 1.2.0
     * @param int $post_id 文章ID
     * @param array $rss_item RSS条目数据
     * @param string $content_hash 内容哈希
     * @return bool 更新成功与否
     */
    private function update_chiral_data_from_rss( $post_id, $rss_item, $content_hash ) {
        $publish_date = $this->parse_rss_date( $rss_item['pubDate'] );

        $post_data = array(
            'ID' => $post_id,
            'post_title' => sanitize_text_field( $rss_item['title'] ),
            'post_content' => wp_kses_post( $rss_item['content'] ),
            'post_excerpt' => wp_trim_words( strip_tags( $rss_item['description'] ), 55 ),
            'post_modified' => current_time( 'mysql' ),
            'post_modified_gmt' => current_time( 'mysql', 1 )
        );

        $result = wp_update_post( $post_data, true );

        if ( is_wp_error( $result ) ) {
            return false;
        }

        // 更新元数据
        update_post_meta( $post_id, '_chiral_content_hash', $content_hash );
        update_post_meta( $post_id, '_chiral_last_crawl_check', time() );

        // 处理头图（与新建文章一样的逻辑）
        if ( !empty( $rss_item['featured_image'] ) ) {
            error_log( 'Chiral Hub RSS: Processing featured image for updated post ' . $post_id . ': ' . $rss_item['featured_image'] );
            $result = $this->set_featured_image_from_url( $post_id, $rss_item['featured_image'] );
            if ( is_wp_error( $result ) ) {
                error_log( 'Chiral Hub RSS: Failed to set featured image for updated post: ' . $result->get_error_message() );
            } else {
                error_log( 'Chiral Hub RSS: Successfully set featured image for updated post with attachment ID: ' . $result );
            }
        } else {
            error_log( 'Chiral Hub RSS: No featured image found in RSS item for updated post ' . $post_id );
        }

        return true;
    }

    /**
     * 解析RSS日期
     *
     * @since 1.2.0
     * @param string $rss_date RSS日期字符串
     * @return string MySQL日期格式
     */
    private function parse_rss_date( $rss_date ) {
        if ( empty( $rss_date ) ) {
            return current_time( 'mysql' );
        }

        $timestamp = strtotime( $rss_date );
        if ( $timestamp === false ) {
            return current_time( 'mysql' );
        }

        return date( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * 获取默认文章状态
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     * @return string 文章状态
     */
    private function get_default_post_status( $porter_user_id ) {
        $options = get_option( $this->plugin_name . '_options' );
        $registration_policy = isset( $options['new_porter_registration'] ) ? $options['new_porter_registration'] : 'default_status';
        
        return ( $registration_policy === 'pending' ) ? 'pending' : 'publish';
    }

    /**
     * 记录RSS错误
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     * @param string $error_type 错误类型
     * @param string $error_message 错误消息
     * @param array $context 上下文信息
     */
    private function log_rss_error( $porter_user_id, $error_type, $error_message, $context = array() ) {
        $log_entry = array(
            'timestamp' => time(),
            'user_id' => $porter_user_id,
            'error_type' => $error_type,
            'message' => $error_message,
            'context' => $context
        );

        $existing_log = get_user_meta( $porter_user_id, '_chiral_rss_error_log', true );
        if ( !is_array( $existing_log ) ) {
            $existing_log = array();
        }

        $existing_log[] = $log_entry;

        // 只保留最近的50条错误记录
        if ( count( $existing_log ) > 50 ) {
            $existing_log = array_slice( $existing_log, -50 );
        }

        update_user_meta( $porter_user_id, '_chiral_rss_error_log', $existing_log );
    }

    /**
     * 处理Sitemap导入任务
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     * @param array $urls URL列表
     */
    public function process_sitemap_import( $porter_user_id, $urls = null ) {
        $import_status = get_user_meta( $porter_user_id, '_chiral_import_status', true );
        
        if ( !$import_status ) {
            // 导入状态不存在，可能已被取消
            delete_user_meta( $porter_user_id, '_chiral_import_in_progress' );
            delete_user_meta( $porter_user_id, '_chiral_import_urls' );
            return;
        }

        // 如果URLs参数为空，从用户元数据中获取
        if ( empty( $urls ) ) {
            $urls = get_user_meta( $porter_user_id, '_chiral_import_urls', true );
            if ( empty( $urls ) ) {
                // 无法获取URLs，取消导入
                delete_user_meta( $porter_user_id, '_chiral_import_in_progress' );
                delete_user_meta( $porter_user_id, '_chiral_import_urls' );
                return;
            }
        }

        $batch_size = 5; // 每批处理5个URL
        $start_index = $import_status['processed_items'] ?? 0;
        $batch_urls = array_slice( $urls, $start_index, $batch_size );

        if ( empty( $batch_urls ) ) {
            // 所有URL都已处理完成
            delete_user_meta( $porter_user_id, '_chiral_import_in_progress' );
            delete_user_meta( $porter_user_id, '_chiral_import_urls' );
            $import_status['is_importing'] = false;
            $import_status['progress'] = 100;
            $import_status['current_url'] = '';
            update_user_meta( $porter_user_id, '_chiral_import_status', $import_status );
            return;
        }

        foreach ( $batch_urls as $url ) {
            $import_status['current_url'] = $url;
            $import_status['last_update'] = time();
            update_user_meta( $porter_user_id, '_chiral_import_status', $import_status );

            // 获取页面内容并尝试提取文章信息
            $response = wp_remote_get( $url, array(
                'timeout' => 30,
                'user-agent' => 'Chiral Hub RSS Crawler/' . $this->version
            ) );

            if ( is_wp_error( $response ) ) {
                $import_status['error_count']++;
                $this->log_rss_error( $porter_user_id, 'sitemap_fetch_failed', $response->get_error_message(), array( 'url' => $url ) );
            } else {
                // 简单的内容提取（这里可以根据需要改进）
                $content = wp_remote_retrieve_body( $response );
                $result = $this->create_post_from_html( $porter_user_id, $url, $content );
                
                if ( is_wp_error( $result ) ) {
                    $import_status['error_count']++;
                    $this->log_rss_error( $porter_user_id, 'post_creation_failed', $result->get_error_message(), array( 'url' => $url ) );
                } else {
                    $import_status['success_count']++;
                }
            }

            $import_status['processed_items']++;
            $import_status['progress'] = round( ( $import_status['processed_items'] / $import_status['total_items'] ) * 100, 2 );
            update_user_meta( $porter_user_id, '_chiral_import_status', $import_status );
        }

        // 如果还有更多URL需要处理，安排下一批
        if ( $import_status['processed_items'] < $import_status['total_items'] ) {
            wp_schedule_single_event( time() + 5, 'chiral_hub_process_sitemap_import', array( $porter_user_id, $urls ) );
        } else {
            // 导入完成
            delete_user_meta( $porter_user_id, '_chiral_import_in_progress' );
            delete_user_meta( $porter_user_id, '_chiral_import_urls' );
            $import_status['is_importing'] = false;
            $import_status['progress'] = 100;
            $import_status['current_url'] = '';
            update_user_meta( $porter_user_id, '_chiral_import_status', $import_status );
        }
    }

    /**
     * 从HTML内容创建文章
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     * @param string $url 源URL
     * @param string $html_content HTML内容
     * @return int|WP_Error 文章ID或错误
     */
    private function create_post_from_html( $porter_user_id, $url, $html_content ) {
        // 简单的HTML解析来提取标题和内容
        if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html_content, $title_matches ) ) {
            $original_title = html_entity_decode( strip_tags( $title_matches[1] ), ENT_QUOTES, 'UTF-8' );
        } else {
            $original_title = parse_url( $url, PHP_URL_PATH );
        }

        // 尝试提取主要内容
        $content = '';
        if ( preg_match( '/<article[^>]*>(.*?)<\/article>/is', $html_content, $article_matches ) ) {
            $content = $article_matches[1];
        } elseif ( preg_match( '/<main[^>]*>(.*?)<\/main>/is', $html_content, $main_matches ) ) {
            $content = $main_matches[1];
        } else {
            // 简单的body内容提取
            if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html_content, $body_matches ) ) {
                $content = $body_matches[1];
            } else {
                $content = $html_content;
            }
        }

        // 清理内容
        $content = wp_kses_post( $content );

        // 提取头图
        $featured_image = $this->extract_featured_image_from_html( $html_content, $url );

        // 生成摘要
        $excerpt = $this->generate_excerpt_from_content( $content );

        $node_id = get_user_meta( $porter_user_id, '_chiral_node_id', true );
        $post_status = $this->get_default_post_status( $porter_user_id );
        
        $post_data = array(
            'post_title' => sanitize_text_field( $original_title ),
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => $post_status,
            'post_author' => $porter_user_id,
            'post_type' => Chiral_Hub_CPT::CPT_SLUG,
            'meta_input' => array(
                'chiral_source_url' => $url,
                '_chiral_node_id' => $node_id,
                '_chiral_source_type' => 'rss',
                '_chiral_sync_method' => 'sitemap_import',
                '_chiral_content_hash' => md5( $content ),
                '_chiral_imported_at' => time()
            )
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // 处理头图
        if ( !empty( $featured_image ) ) {
            $this->set_featured_image_from_url( $post_id, $featured_image );
        }

        return $post_id;
    }

    /**
     * 过滤非文章类URL
     *
     * @since 1.2.0
     * @param array $urls URL数组
     * @param int $porter_user_id Porter用户ID
     * @return array 过滤后的URL数组
     */
    private function filter_article_urls( $urls, $porter_user_id = 0 ) {
        $original_count = count( $urls );
        $filtered_urls = array();
        $excluded_urls = array();
        
        foreach ( $urls as $url ) {
            if ( $this->is_article_url( $url, $porter_user_id ) ) {
                $filtered_urls[] = $url;
            } else {
                $excluded_urls[] = $url;
            }
        }
        
        $filtered_count = count( $filtered_urls );
        $excluded_count = count( $excluded_urls );
        
        // 记录过滤统计信息
        if ( $porter_user_id > 0 ) {
            $filter_mode = get_user_meta( $porter_user_id, '_chiral_url_filter_mode', true );
            if ( empty( $filter_mode ) ) {
                $filter_mode = 'exclude';
            }
            
            $filter_stats = array(
                'original_count' => $original_count,
                'filtered_count' => $filtered_count,
                'excluded_count' => $excluded_count,
                'filter_mode' => $filter_mode,
                'excluded_urls' => array_slice( $excluded_urls, 0, 20 ), // 只保留前20个排除的URL作为示例
                'filter_time' => time()
            );
            update_user_meta( $porter_user_id, '_chiral_last_filter_stats', $filter_stats );
        }
        
        return $filtered_urls;
    }

    /**
     * 判断是否为文章类URL
     *
     * @since 1.2.0
     * @param string $url URL
     * @param int $porter_user_id Porter用户ID
     * @return bool 是否为文章类URL
     */
    private function is_article_url( $url, $porter_user_id = 0 ) {
        // 解析URL路径
        $parsed_url = parse_url( $url );
        if ( !isset( $parsed_url['path'] ) ) {
            return false; // 没有路径的URL通常不是文章
        }

        $path = trim( $parsed_url['path'], '/' );
        
        // 如果路径为空，通常是首页，排除
        if ( empty( $path ) ) {
            return false;
        }

        // 将路径分割为段
        $path_segments = explode( '/', $path );
        
        // 获取用户配置的过滤模式
        $filter_mode = 'exclude'; // 默认排除模式
        if ( $porter_user_id > 0 ) {
            $user_filter_mode = get_user_meta( $porter_user_id, '_chiral_url_filter_mode', true );
            if ( !empty( $user_filter_mode ) ) {
                $filter_mode = $user_filter_mode;
            }
        }

        if ( $filter_mode === 'include' ) {
            // 包含模式：只有包含指定slug的URL才被接受
            return $this->check_include_mode( $path_segments, $porter_user_id );
        } else {
            // 排除模式：使用原有的排除逻辑
            return $this->check_exclude_mode( $url, $path_segments, $porter_user_id );
        }
    }

    /**
     * 检查包含模式过滤
     *
     * @since 1.2.0
     * @param array $path_segments 路径段数组
     * @param int $porter_user_id Porter用户ID
     * @return bool 是否为文章URL
     */
    private function check_include_mode( $path_segments, $porter_user_id ) {
        // 获取用户配置的包含规则
        $include_slugs = '';
        if ( $porter_user_id > 0 ) {
            $include_slugs = get_user_meta( $porter_user_id, '_chiral_url_include_slugs', true );
        }
        
        // 解析包含规则
        $include_segments = array();
        if ( !empty( $include_slugs ) ) {
            $include_segments = array_map( 'trim', explode( ',', strtolower( $include_slugs ) ) );
        }
        
        // 如果没有配置包含规则，使用默认规则
        if ( empty( $include_segments ) ) {
            $include_segments = array( 'post', 'posts', 'article', 'articles', 'blog', 'news' );
        }
        
        // 检查路径段是否包含任何指定的文章slug，并且该slug后面还有内容
        for ( $i = 0; $i < count( $path_segments ); $i++ ) {
            $segment = strtolower( $path_segments[$i] );
            
            if ( in_array( $segment, $include_segments ) ) {
                // 找到匹配的文章slug，检查后面是否还有路径段
                if ( $i >= count( $path_segments ) - 1 ) {
                    // slug是最后一个段，这通常是归档页面，拒绝
                    continue;
                }
                
                $next_segment = trim( $path_segments[$i + 1] );
                if ( empty( $next_segment ) ) {
                    // 后面的段为空，拒绝
                    continue;
                }
                
                // 检查下一个段的类型
                if ( !is_numeric( $next_segment ) ) {
                    // 后面是非数字内容，很可能是文章标题slug，接受
                    return true;
                } else {
                    // 后面是数字，需要进一步判断
                    $number = intval( $next_segment );
                    
                    // 检查是否有第三个段（数字后面还有内容）
                    if ( $i < count( $path_segments ) - 2 ) {
                        $third_segment = trim( $path_segments[$i + 2] );
                        if ( !empty( $third_segment ) ) {
                            // 类似 /blog/2024/article-title 的模式，接受
                            return true;
                        }
                    }
                    
                    // 单独的数字：判断是否是合理的文章ID
                    if ( $number > 0 && $number <= 10000 ) {
                        // 小数字更可能是文章ID，接受
                        return true;
                    }
                    
                    // 大数字可能是日期或分页，继续检查其他slug
                }
            }
        }
        
        // 没有找到匹配的文章slug或slug后面没有合适的内容
        return false;
    }

    /**
     * 从RSS条目中提取头图
     *
     * @since 1.2.0
     * @param SimpleXMLElement $item RSS条目
     * @return string|null 头图URL或null
     */
    private function extract_featured_image_from_rss_item( $item ) {
        error_log( 'Chiral Hub RSS: Extracting featured image from RSS item' );
        
        // 检查media namespace
        $media = $item->children( 'http://search.yahoo.com/mrss/' );
        if ( isset( $media->content ) && isset( $media->content['url'] ) ) {
            $url = (string) $media->content['url'];
            error_log( 'Chiral Hub RSS: Found media:content URL: ' . $url );
            if ( $this->is_valid_image_url( $url ) ) {
                error_log( 'Chiral Hub RSS: Media URL is valid, returning: ' . $url );
                return $url;
            }
        }

        // 检查enclosure
        if ( isset( $item->enclosure ) && isset( $item->enclosure['url'] ) && isset( $item->enclosure['type'] ) ) {
            $type = (string) $item->enclosure['type'];
            if ( strpos( $type, 'image/' ) === 0 ) {
                $url = (string) $item->enclosure['url'];
                error_log( 'Chiral Hub RSS: Found enclosure image URL: ' . $url );
                if ( $this->is_valid_image_url( $url ) ) {
                    error_log( 'Chiral Hub RSS: Enclosure URL is valid, returning: ' . $url );
                    return $url;
                }
            }
        }

        // 从content:encoded或description中提取第一个图片
        $content = '';
        if ( isset( $item->children( 'http://purl.org/rss/1.0/modules/content/' )->encoded ) ) {
            $content = (string) $item->children( 'http://purl.org/rss/1.0/modules/content/' )->encoded;
            error_log( 'Chiral Hub RSS: Using content:encoded for image extraction' );
        } else {
            $content = (string) $item->description;
            error_log( 'Chiral Hub RSS: Using description for image extraction' );
        }

        $extracted_image = $this->extract_first_image_from_content( $content );
        if ( $extracted_image ) {
            // 确保HTML实体被正确解码
            $decoded_image = html_entity_decode( $extracted_image, ENT_QUOTES, 'UTF-8' );
            error_log( 'Chiral Hub RSS: Extracted image from content: ' . $decoded_image );
            return $decoded_image;
        } else {
            error_log( 'Chiral Hub RSS: No image found in content' );
            return null;
        }
    }

    /**
     * 从Atom条目中提取头图
     *
     * @since 1.2.0
     * @param SimpleXMLElement $entry Atom条目
     * @return string|null 头图URL或null
     */
    private function extract_featured_image_from_atom_entry( $entry ) {
        // 检查link rel="enclosure"
        if ( isset( $entry->link ) ) {
            foreach ( $entry->link as $link ) {
                if ( isset( $link['rel'] ) && (string) $link['rel'] === 'enclosure' && isset( $link['type'] ) ) {
                    $type = (string) $link['type'];
                    if ( strpos( $type, 'image/' ) === 0 && isset( $link['href'] ) ) {
                        $url = (string) $link['href'];
                        if ( $this->is_valid_image_url( $url ) ) {
                            return $url;
                        }
                    }
                }
            }
        }

        // 从content中提取第一个图片
        $content = '';
        if ( isset( $entry->content ) ) {
            $content = (string) $entry->content;
        } else {
            $content = (string) $entry->summary;
        }

        $extracted_image = $this->extract_first_image_from_content( $content );
        if ( $extracted_image ) {
            // 确保HTML实体被正确解码
            return html_entity_decode( $extracted_image, ENT_QUOTES, 'UTF-8' );
        }
        
        return null;
    }

    /**
     * 从HTML内容中提取头图
     *
     * @since 1.2.0
     * @param string $html_content HTML内容
     * @param string $base_url 基础URL，用于转换相对URL
     * @return string|null 头图URL或null
     */
    private function extract_featured_image_from_html( $html_content, $base_url = '' ) {
        // 检查Open Graph图片
        if ( preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html_content, $matches ) ) {
            if ( $this->is_valid_image_url( $matches[1] ) ) {
                return $this->make_absolute_url( $matches[1], $base_url );
            }
        }

        // 检查Twitter Card图片
        if ( preg_match( '/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $html_content, $matches ) ) {
            if ( $this->is_valid_image_url( $matches[1] ) ) {
                return $this->make_absolute_url( $matches[1], $base_url );
            }
        }

        // 查找文章内容区域的第一个图片
        $content_patterns = array(
            '/<article[^>]*>(.*?)<\/article>/is',
            '/<main[^>]*>(.*?)<\/main>/is',
            '/<div[^>]*class=[^>]*entry-content[^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class=[^>]*post-content[^>]*>(.*?)<\/div>/is'
        );

        foreach ( $content_patterns as $pattern ) {
            if ( preg_match( $pattern, $html_content, $matches ) ) {
                $first_image = $this->extract_first_image_from_content( $matches[1], $base_url );
                if ( $first_image ) {
                    return $first_image;
                }
            }
        }

        // 如果没有找到，从整个HTML中提取第一个图片
        return $this->extract_first_image_from_content( $html_content, $base_url );
    }

    /**
     * 从内容中提取第一个图片URL
     *
     * @since 1.2.0
     * @param string $content 内容
     * @param string $base_url 基础URL，用于转换相对URL
     * @return string|null 图片URL或null
     */
    private function extract_first_image_from_content( $content, $base_url = '' ) {
        if ( empty( $content ) ) {
            return null;
        }

        // 查找img标签
        if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
            $url = $matches[1];
            if ( $this->is_valid_image_url( $url ) ) {
                return $this->make_absolute_url( $url, $base_url );
            }
        }

        return null;
    }

    /**
     * 检查是否为有效的图片URL
     *
     * @since 1.2.0
     * @param string $url URL
     * @return bool 是否为有效图片URL
     */
    private function is_valid_image_url( $url ) {
        if ( empty( $url ) ) {
            error_log( 'Chiral Hub RSS: URL is empty' );
            return false;
        }

        // HTML实体解码已在图片提取阶段完成，这里直接验证

        // 检查URL格式
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) && ! $this->is_relative_url( $url ) ) {
            error_log( 'Chiral Hub RSS: Invalid URL format: ' . $url );
            return false;
        }

        // 检查文件扩展名
        $parsed_url = parse_url( $url );
        if ( isset( $parsed_url['path'] ) ) {
            $extension = strtolower( pathinfo( $parsed_url['path'], PATHINFO_EXTENSION ) );
            $valid_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp' );
            if ( in_array( $extension, $valid_extensions ) ) {
                error_log( 'Chiral Hub RSS: Valid image extension found (' . $extension . '): ' . $url );
                return true;
            }
        }

        // 如果没有明确的扩展名，检查URL是否包含图片相关关键词
        $image_keywords = array( 'image', 'img', 'photo', 'picture', 'pic', 'notion.so/image' );
        $url_lower = strtolower( $url );
        foreach ( $image_keywords as $keyword ) {
            if ( strpos( $url_lower, $keyword ) !== false ) {
                error_log( 'Chiral Hub RSS: Image keyword "' . $keyword . '" found in URL: ' . $url );
                return true;
            }
        }

        // 检查常见的图片CDN域名
        $image_domains = array( 'images.unsplash.com', 'cdn.', 'img.', 'static.', 'media.' );
        foreach ( $image_domains as $domain ) {
            if ( strpos( $url_lower, $domain ) !== false ) {
                error_log( 'Chiral Hub RSS: Image domain "' . $domain . '" found in URL: ' . $url );
                return true;
            }
        }

        error_log( 'Chiral Hub RSS: URL did not pass any validation checks: ' . $url );
        return false;
    }

    /**
     * 检查是否为相对URL
     *
     * @since 1.2.0
     * @param string $url URL
     * @return bool 是否为相对URL
     */
    private function is_relative_url( $url ) {
        return ! empty( $url ) && ( strpos( $url, '/' ) === 0 || strpos( $url, './' ) === 0 || strpos( $url, '../' ) === 0 );
    }

    /**
     * 将相对URL转换为绝对URL
     *
     * @since 1.2.0
     * @param string $url 可能的相对URL
     * @param string $base_url 基础URL（可选）
     * @return string 绝对URL
     */
    private function make_absolute_url( $url, $base_url = '' ) {
        if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return $url; // 已经是绝对URL
        }

        if ( empty( $base_url ) ) {
            // 尝试从当前处理的URL中获取base_url
            // 这需要在调用时传入base_url参数
            return $url;
        }

        $parsed_base = parse_url( $base_url );
        $scheme = isset( $parsed_base['scheme'] ) ? $parsed_base['scheme'] : 'https';
        $host = isset( $parsed_base['host'] ) ? $parsed_base['host'] : '';

        if ( strpos( $url, '//' ) === 0 ) {
            return $scheme . ':' . $url;
        }

        if ( strpos( $url, '/' ) === 0 ) {
            return $scheme . '://' . $host . $url;
        }

        // 相对路径
        $path = isset( $parsed_base['path'] ) ? dirname( $parsed_base['path'] ) : '';
        if ( $path === '.' ) {
            $path = '';
        }
        return $scheme . '://' . $host . $path . '/' . $url;
    }

    /**
     * 从内容生成摘要
     *
     * @since 1.2.0
     * @param string $content 内容
     * @return string 摘要
     */
    private function generate_excerpt_from_content( $content ) {
        if ( empty( $content ) ) {
            return '';
        }

        // 移除HTML标签
        $text = wp_strip_all_tags( $content );
        
        // 移除多余的空白字符
        $text = preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );

        // 生成摘要（55个词，WordPress默认）
        return wp_trim_words( $text, 55 );
    }

    /**
     * 为文章设置头图
     *
     * @since 1.2.0
     * @param int $post_id 文章ID
     * @param string $image_url 图片URL
     * @return int|WP_Error 附件ID或错误
     */
    private function set_featured_image_from_url( $post_id, $image_url ) {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
        }

        if ( empty( $post_id ) || empty( $image_url ) || ! get_post( $post_id ) ) {
            return new WP_Error( 'invalid_inputs', 'Post ID or image URL is empty or post not found' );
        }

        // 如果已经有头图，跳过
        if ( has_post_thumbnail( $post_id ) ) {
            return get_post_thumbnail_id( $post_id );
        }

        // 对Notion图片URL进行特殊处理
        $processed_url = $this->process_notion_image_url( $image_url );
        
        // 检查图片URL是否可访问，并跟随重定向
        $final_url = $this->follow_redirects_to_final_url( $processed_url );
        if ( is_wp_error( $final_url ) ) {
            return $final_url;
        }

        // 为Notion图片设置自定义的HTTP参数
        if ( strpos( $final_url, 'notion.so' ) !== false ) {
            add_filter( 'http_request_args', array( $this, 'modify_notion_image_request' ), 10, 2 );
        }
        
        // 下载并设置头图
        $attachment_id = media_sideload_image( $final_url, $post_id, null, 'id' );
        
        // 移除过滤器
        if ( strpos( $final_url, 'notion.so' ) !== false ) {
            remove_filter( 'http_request_args', array( $this, 'modify_notion_image_request' ), 10 );
        }
        
        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        if ( set_post_thumbnail( $post_id, $attachment_id ) ) {
            return $attachment_id;
        } else {
            return new WP_Error( 'thumbnail_failed', 'Failed to set post thumbnail' );
        }
    }

    /**
     * 处理Notion图片URL的特殊格式
     *
     * @since 1.2.0
     * @param string $image_url 原始图片URL
     * @return string 处理后的图片URL
     */
    private function process_notion_image_url( $image_url ) {
        // 如果不是Notion图片URL，直接返回
        if ( strpos( $image_url, 'notion.so/image' ) === false ) {
            return $image_url;
        }

        error_log( 'Chiral Hub RSS: Processing Notion URL: ' . $image_url );
        
        // 直接返回，HTML实体解码已经在提取阶段完成
        return $image_url;
    }

    /**
     * 跟随重定向获取最终的图片URL
     *
     * @since 1.2.0
     * @param string $url 原始URL
     * @return string|WP_Error 最终URL或错误
     */
    private function follow_redirects_to_final_url( $url ) {
        // 对于Notion URL，我们需要使用特殊的处理方式
        if ( strpos( $url, 'notion.so/image' ) !== false ) {
            // 检查原始URL是否可以通过正确的User-Agent访问
            $response = wp_remote_head( $url, array(
                'timeout' => 30,
                'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
                'headers' => array(
                    'Accept' => 'image/*,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate'
                )
            ) );
            
            if ( is_wp_error( $response ) ) {
                error_log( 'Chiral Hub RSS: Error checking Notion URL: ' . $response->get_error_message() );
                return $response;
            }
            
            $response_code = wp_remote_retrieve_response_code( $response );
            
            // Notion URL可能返回302，这是正常的
            if ( in_array( $response_code, array( 200, 301, 302, 303, 307, 308 ) ) ) {
                error_log( 'Chiral Hub RSS: Notion URL is accessible with status: ' . $response_code );
                return $url; // 返回原始URL，让media_sideload_image处理重定向
            } else {
                return new WP_Error( 'notion_image_not_accessible', "Notion image URL returned status code: {$response_code}" );
            }
        }
        
        // 对于非Notion URL，直接检查（HTML实体解码已在提取阶段完成）
        $response = wp_remote_head( $url, array( 'timeout' => 30 ) );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'Chiral Hub RSS: Error checking URL: ' . $response->get_error_message() );
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        
        if ( $response_code === 200 ) {
            $content_type = wp_remote_retrieve_header( $response, 'content-type' );
            if ( strpos( $content_type, 'image/' ) !== 0 ) {
                return new WP_Error( 'not_an_image', "URL does not point to an image. Content-Type: {$content_type}" );
            }
            
            error_log( 'Chiral Hub RSS: Image URL is accessible: ' . $url );
            return $url;
        } else {
            return new WP_Error( 'image_not_accessible', "Image URL returned status code: {$response_code}" );
        }
    }

    /**
     * 修改Notion图片请求的HTTP参数
     *
     * @since 1.2.0
     * @param array $args HTTP请求参数
     * @param string $url 请求URL
     * @return array 修改后的参数
     */
    public function modify_notion_image_request( $args, $url ) {
        if ( strpos( $url, 'notion.so' ) !== false || strpos( $url, 'notionusercontent.com' ) !== false ) {
            $args['user-agent'] = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' );
            $args['headers']['Accept'] = 'image/*,*/*;q=0.8';
            $args['headers']['Accept-Language'] = 'en-US,en;q=0.5';
            $args['headers']['Accept-Encoding'] = 'gzip, deflate';
            $args['timeout'] = 30;
            
            error_log( 'Chiral Hub RSS: Modified request for Notion URL: ' . $url );
        }
        
        return $args;
    }

    /**
     * 检查排除模式过滤
     *
     * @since 1.2.0
     * @param string $url 完整URL
     * @param array $path_segments 路径段数组
     * @param int $porter_user_id Porter用户ID
     * @return bool 是否为文章URL
     */
    private function check_exclude_mode( $url, $path_segments, $porter_user_id ) {
        $parsed_url = parse_url( $url );
        $path = trim( $parsed_url['path'], '/' );
        
        // 获取用户配置的排除规则
        $user_exclusions = '';
        if ( $porter_user_id > 0 ) {
            $user_exclusions = get_user_meta( $porter_user_id, '_chiral_url_exclusions', true );
        }
        
        // 解析用户排除规则
        $user_excluded_segments = array();
        if ( !empty( $user_exclusions ) ) {
            $user_excluded_segments = array_map( 'trim', explode( ',', strtolower( $user_exclusions ) ) );
        }
        
        // 定义默认要排除的路径段
        $default_excluded_segments = array(
            // 分类和标签相关
            'tag', 'tags', 'category', 'categories', 'cat',
            // 归档相关
            'archive', 'archives', 'date', 'year', 'month',
            // 页面相关
            'page', 'pages', 'static',
            // 作者相关
            'author', 'authors', 'user', 'users',
            // 搜索和其他功能页面
            'search', 'feed', 'rss', 'atom', 'sitemap',
            // 管理和API相关
            'admin', 'api', 'wp-content', 'wp-admin', 'wp-json',
            // 常见的非文章页面
            'about', 'contact', 'privacy', 'terms', 'disclaimer',
            'sitemap.xml', 'robots.txt', 'favicon.ico',
            // 多语言相关
            'zh', 'en', 'zh-cn', 'zh-tw', 'en-us',
            // 其他常见排除项
            'index', 'home', 'blog'
        );

        // 合并用户排除规则和默认排除规则
        $excluded_segments = array_merge( $default_excluded_segments, $user_excluded_segments );
        $excluded_segments = array_unique( $excluded_segments );

        // 检查每个路径段是否在排除列表中
        foreach ( $path_segments as $segment ) {
            $segment = strtolower( $segment );
            if ( in_array( $segment, $excluded_segments ) ) {
                return false;
            }
        }

        // 检查文件扩展名，排除非HTML文件
        $excluded_extensions = array( 'xml', 'txt', 'ico', 'png', 'jpg', 'jpeg', 'gif', 'css', 'js', 'pdf' );
        $extension = pathinfo( $path, PATHINFO_EXTENSION );
        if ( !empty( $extension ) && in_array( strtolower( $extension ), $excluded_extensions ) ) {
            return false;
        }

        // 检查是否包含查询参数（通常非文章页面会有查询参数）
        if ( isset( $parsed_url['query'] ) && !empty( $parsed_url['query'] ) ) {
            // 解析查询参数
            parse_str( $parsed_url['query'], $query_params );
            
            // 排除包含特定查询参数的URL
            $excluded_params = array( 'page_id', 'cat', 'tag', 'author', 's', 'search', 'paged' );
            foreach ( $excluded_params as $param ) {
                if ( isset( $query_params[$param] ) ) {
                    return false;
                }
            }
        }

        // 检查路径长度 - 通常文章URL有一定长度
        if ( strlen( $path ) < 3 ) {
            return false; // 太短的路径通常不是文章
        }

        // 检查是否以数字结尾（可能是分页）
        if ( preg_match( '/\/\d+\/?$/', $path ) ) {
            // 如果是纯数字结尾，可能是分页或ID，需要进一步判断
            $last_segment = end( $path_segments );
            if ( is_numeric( $last_segment ) && $last_segment > 1 ) {
                // 可能是分页，排除
                return false;
            }
        }

        // 如果通过了所有检查，认为是文章URL
        return true;
    }

    /**
     * 处理每小时RSS同步任务
     *
     * @since 1.2.0
     */
    public function handle_hourly_rss_sync() {
        $rss_porters = $this->get_rss_porters_for_sync();
        
        if ( empty( $rss_porters ) ) {
            return;
        }

        foreach ( $rss_porters as $porter_user_id ) {
            // 检查上次同步时间，避免过于频繁的同步
            $last_sync = get_user_meta( $porter_user_id, '_chiral_rss_last_sync', true );
            if ( $last_sync && ( time() - $last_sync ) < 3300 ) { // 55分钟内已同步过，跳过
                continue;
            }

            // 执行RSS同步
            $this->process_rss_updates( $porter_user_id );
            
            // 记录同步时间
            update_user_meta( $porter_user_id, '_chiral_rss_last_sync', time() );
        }
    }

    /**
     * 处理每日RSS内容巡查任务
     *
     * @since 1.2.0
     */
    public function handle_daily_rss_patrol() {
        $rss_porters = $this->get_rss_porters_for_sync();
        
        if ( empty( $rss_porters ) ) {
            return;
        }

        foreach ( $rss_porters as $porter_user_id ) {
            // 清理过期的错误日志（保留30天）
            $this->cleanup_error_logs( $porter_user_id, 30 );
            
            // 清理过期的导入状态（保留7天）
            $this->cleanup_import_status( $porter_user_id, 7 );
            
            // 检查RSS URL的有效性
            $this->validate_rss_health( $porter_user_id );
        }
    }

    /**
     * 获取需要RSS同步的Porter用户列表
     *
     * @since 1.2.0
     * @return array Porter用户ID数组
     */
    private function get_rss_porters_for_sync() {
        $users = get_users( array(
            'role' => 'chiral_porter',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_chiral_sync_mode',
                    'value' => 'rss',
                    'compare' => '='
                ),
                array(
                    'key' => '_chiral_rss_url',
                    'value' => '',
                    'compare' => '!='
                )
            )
        ) );

        return wp_list_pluck( $users, 'ID' );
    }

    /**
     * 清理过期的错误日志
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     * @param int $days_to_keep 保留天数
     */
    private function cleanup_error_logs( $porter_user_id, $days_to_keep = 30 ) {
        $error_log = get_user_meta( $porter_user_id, '_chiral_rss_error_log', true );
        if ( !is_array( $error_log ) || empty( $error_log ) ) {
            return;
        }

        $cutoff_time = time() - ( $days_to_keep * DAY_IN_SECONDS );
        $cleaned_log = array();

        foreach ( $error_log as $log_entry ) {
            if ( isset( $log_entry['timestamp'] ) && $log_entry['timestamp'] >= $cutoff_time ) {
                $cleaned_log[] = $log_entry;
            }
        }

        if ( count( $cleaned_log ) !== count( $error_log ) ) {
            update_user_meta( $porter_user_id, '_chiral_rss_error_log', $cleaned_log );
        }
    }

    /**
     * 清理过期的导入状态
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     * @param int $days_to_keep 保留天数
     */
    private function cleanup_import_status( $porter_user_id, $days_to_keep = 7 ) {
        $import_status = get_user_meta( $porter_user_id, '_chiral_import_status', true );
        if ( !is_array( $import_status ) ) {
            return;
        }

        $cutoff_time = time() - ( $days_to_keep * DAY_IN_SECONDS );
        $last_update = $import_status['last_update'] ?? 0;

        // 如果导入状态太旧且不在进行中，清理它
        if ( $last_update < $cutoff_time && empty( $import_status['is_importing'] ) ) {
            delete_user_meta( $porter_user_id, '_chiral_import_status' );
            delete_user_meta( $porter_user_id, '_chiral_import_in_progress' );
            delete_user_meta( $porter_user_id, '_chiral_import_urls' );
        }
    }

    /**
     * 验证RSS健康状况
     *
     * @since 1.2.0
     * @param int $porter_user_id Porter用户ID
     */
    private function validate_rss_health( $porter_user_id ) {
        $rss_url = get_user_meta( $porter_user_id, '_chiral_rss_url', true );
        if ( empty( $rss_url ) ) {
            return;
        }

        // 执行简单的连接测试
        $response = wp_remote_get( $rss_url, array(
            'timeout' => 15,
            'user-agent' => 'Chiral Hub RSS Health Check/' . $this->version
        ) );

        $health_status = array(
            'last_check' => time(),
            'is_healthy' => true,
            'status_code' => 0,
            'error_message' => ''
        );

        if ( is_wp_error( $response ) ) {
            $health_status['is_healthy'] = false;
            $health_status['error_message'] = $response->get_error_message();
            $this->log_rss_error( $porter_user_id, 'health_check_failed', $response->get_error_message() );
        } else {
            $status_code = wp_remote_retrieve_response_code( $response );
            $health_status['status_code'] = $status_code;
            
            if ( $status_code !== 200 ) {
                $health_status['is_healthy'] = false;
                $health_status['error_message'] = "HTTP $status_code response";
                $this->log_rss_error( $porter_user_id, 'health_check_http_error', "RSS URL returned HTTP $status_code" );
            }
        }

        update_user_meta( $porter_user_id, '_chiral_rss_health', $health_status );
    }
}
