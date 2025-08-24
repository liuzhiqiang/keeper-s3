<?php
// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit;
}

// 启用 AWS SDK
//require_once S3KEEPER_PLUGIN_DIR.'/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;


// 在附件列表中添加存储位置列
add_filter('manage_media_columns', 's3keeper_add_storage_column');
function s3keeper_add_storage_column($columns)
{
    $columns['s3keep_storage_location'] = __('Storage Location', 'keeper-s3');
    return $columns;
}

// 显示附件存储位置
add_action('manage_media_custom_column', 's3keeper_show_storage_location_column', 10, 2);
function s3keeper_show_storage_location_column1($column_name, $post_id)
{
    if ($column_name === 's3keep_storage_location') {
        $storage_location = get_post_meta($post_id, S3KEEP_ATTACHMENT_STORAGE, true);
//        $test = get_post_meta($post_id, '_wp_attachment_metadata');
//        print_r($test);
        echo $storage_location ? esc_html(ucfirst($storage_location)) : '';
    }
}

function s3keeper_show_storage_location_column($column_name, $post_id)
{
    if ($column_name === 's3keep_storage_location') {
        $storage_location = get_post_meta($post_id, S3KEEP_ATTACHMENT_STORAGE, true);
        $options = [
            'local' => 'Local',
            's3' => 'S3',
            'both' => 'Local + S3'
        ];

        echo '<select class="s3keeper-storage-select" data-post-id="' . esc_attr($post_id) . '">';
        foreach ($options as $option => $value) {
            $selected = ($storage_location === $option) ? 'selected' : '';
            echo '<option value="' . esc_attr($option) . '" ' . esc_html($selected) . '>' . esc_html($value) . '</option>';
        }
        echo '</select>';
    }
}

add_action('wp_ajax_s3keeper_get_attachment_statistics', 's3keeper_get_attachment_statistics');
function s3keeper_get_attachment_statistics()
{
    global $wpdb;

    $args = array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => -1, // 获取所有符合条件的附件
        'fields' => 'ids', // 只获取附件的 ID，提高性能
        'meta_query' => array(
            array(
                'key' => S3KEEP_ATTACHMENT_STORAGE,
                'compare' => 'NOT EXISTS', // 检查 S3KEEP_ATTACHMENT_STORAGE 元数据是否存在
            ),
        ),
    );

    $query = new WP_Query($args);
    $attachment_count = $query->found_posts;


    // 获取 S3、本地和两者的附件数量
    $storage_count = [
        's3' => 0,
        'local' => 0,
        'both' => 0,
    ];

    $storage = S3KEEP_ATTACHMENT_STORAGE;

    // 查询附件的存储位置
    // Try to get results from cache
    $cache_key = 's3keeper_storage_stats_' . md5($storage);
    $results = wp_cache_get($cache_key);

// If not found in cache, query the database
    if (false === $results) {
        // Direct database query (necessary for complex queries)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "
            SELECT meta_value, COUNT(*) as count 
            FROM {$wpdb->prefix}postmeta 
            WHERE meta_key = %s 
            GROUP BY meta_value
            ",
                $storage // Dynamic variable
            )
        );

        // Cache the results for future use
        wp_cache_set($cache_key, $results, '', 3600); // Cache for 1 hour
    }

    foreach ($results as $result) {
        if (array_key_exists($result->meta_value, $storage_count)) {
            $storage_count[$result->meta_value] = $result->count;
        }
    }

    $storage_count['local'] += $attachment_count;


    // 返回数据
    wp_send_json_success(['data' => $storage_count]);
}

// 获取插件设置
add_action('wp_ajax_s3keeper_get_settings', 's3keeper_get_settings');
function s3keeper_get_settings()
{
    $settings = array(
        's3_endpoint' => get_option('s3keeper_s3_endpoint', 'https://s3.amazonaws.com'),
        's3_bucket' => get_option('s3keeper_s3_bucket', ''),
        's3_key' => get_option('s3keeper_s3_key', ''),
        's3_secret' => get_option('s3keeper_s3_secret', ''),
        's3_location' => get_option('s3keeper_s3_location', 'local'),
        's3_url' => get_option('s3keeper_s3_url', ''),
        's3_path' => get_option('s3keeper_s3_path', ''),
        's3_region' => get_option('s3keeper_s3_region', 'us-east-1'),
        's3_acl' => get_option('s3keeper_s3_acl', 'public-read'),
        's3_check_success' => get_option('s3keeper_s3_check_success', ''),

    );
    wp_send_json_success($settings);
}

// 更新插件设置
add_action('wp_ajax_s3keeper_update_settings', 's3keeper_update_settings');
function s3keeper_update_settings()
{
    check_ajax_referer('s3keeper_ajax_nonce');

    if (!empty($_POST)) {
        // 遍历所有 POST 数据并更新选项
        foreach ($_POST as $key => $value) {
            $key=sanitize_text_field($key);
            $value=sanitize_text_field($value);
            if ($key === 's3_path') {
                $value = trim($value, '/');
                if (!$value) {
                    $value = 'uploads';
                }
            } elseif ($key == 's3_region') {
                if (!$value) {
                    $value = 'us-east-1';
                }
            } elseif ($key == 's3_acl') {
                if (!$value) {
                    $value = 'public-read';
                }
            }


            if ($key !== 'action') { // 排除 'action' 键
                update_option('s3keeper_' . $key, $value);
            }
        }

        $s3_check_result = s3keep_check_s3();
        if ($s3_check_result['success']) {
            update_option('s3keeper_s3_check_success', 'yes');
        } else {
            update_option('s3keeper_s3_check_success', 'no');
        }
        $response = array_merge(['connection_status' => $s3_check_result]);
        wp_send_json_success($response);

    } else {
        wp_send_json_error();  // 返回错误响应
    }
}

// 在附件编辑页面添加切换存储位置的选项
add_action('post_edit_form_tag', 'add_attachment_storage_select');
function add_attachment_storage_select()
{
    echo '<input type="hidden" name="attachment_storage_action" value="true" />';
}

// 在附件编辑页面显示下拉框，允许选择存储位置
add_action('attachment_fields_to_edit', 'add_attachment_storage_field', 10, 2);
function add_attachment_storage_field($form_fields, $post)
{
    $storage_location = get_post_meta($post->ID, S3KEEP_ATTACHMENT_STORAGE, true);
    $storage_location = $storage_location ? $storage_location : 'local'; // 默认本地存储

    $form_fields['s3keeper_storage_location'] = array(
        'label' => 'Storage Location',
        'input' => 'html',
        'html' => '
            <select name="attachments[' . $post->ID . '][s3keeper_storage_location]">
                <option value="local" ' . selected($storage_location, 'local', false) . '>Local</option>
                <option value="s3" ' . selected($storage_location, 's3', false) . '>S3</option>
                <option value="both" ' . selected($storage_location, 'both', false) . '>Local + S3</option>
                
            </select>
        ',
    );

    return $form_fields;
}

// 保存存储位置设置
add_action('attachment_fields_to_save', 'save_attachment_storage_field', 10, 2);
function save_attachment_storage_field($post, $attachment)
{

//    print_r($post);exit;
    if (isset($attachment['s3keeper_storage_location'])) {
        $old_storage_location = get_post_meta($post['ID'], S3KEEP_ATTACHMENT_STORAGE, true);
        if (!$old_storage_location) {
            $old_storage_location = 'local';
        }

        if ($old_storage_location !== $attachment['s3keeper_storage_location']) {
            s3keeper_single_convert($post['ID'], $old_storage_location, $attachment['s3keeper_storage_location']);
            update_post_meta($post['ID'], S3KEEP_ATTACHMENT_STORAGE, $attachment['s3keeper_storage_location']);
        }


    }
    return $post;
}

//add_action('add_attachment', 's3keeper_wp_insert_attachment', 10, 1);

//function s3keeper_save_custom_value_to_attachment_meta($attachment_id)
//{
//    // 获取之前存储在 session 中的 custom_value
//    if (isset($_ENV['s3keeper_attachment_storage_tmp'])) {
//
//        // 将 custom_value 存储为附件的元数据
//        update_post_meta($attachment_id, S3KEEP_ATTACHMENT_STORAGE, $_ENV['s3keeper_attachment_storage_tmp']);
//
//        if ($_ENV['s3keeper_attachment_storage_tmp'] == 's3') {
//            $file_path = get_attached_file($attachment_id);
//            unlink($file_path);
//        }
//
//        // 清理 session 中的 custom_value
//        unset($_ENV['s3keeper_attachment_storage_tmp']);
//    }
//}

//add_action('wp_insert_attachment', 's3keeper_wp_insert_attachment', 10, 1);
//function s3keeper_wp_insert_attachment($attachment_id)
//{
//    $old_storage_location = get_post_meta($attachment_id, S3KEEP_ATTACHMENT_STORAGE, true);
//    if (!$old_storage_location) {
//        $old_storage_location = 'local';
//    }
//
////    echo $old_storage_location;exit;
//    $s3_location = get_option('s3keeper_s3_location');
//
//    if ($old_storage_location !== $s3_location) {
//        s3keeper_single_convert($attachment_id, $old_storage_location, $s3_location);
//        update_post_meta($attachment_id, S3KEEP_ATTACHMENT_STORAGE, $s3_location);
//    }
//}


################
// functions.php
add_action('wp_ajax_s3keeper_batch_convert_attachments', 's3keeper_batch_convert_attachments');
//add_action('wp_ajax_nopriv_s3keeper_batch_convert_all_attachments', 's3keeper_batch_convert_all_attachments');

function s3keeper_single_convert($id, $source_storage, $target_storage)
{
    // 获取附件的文件路径
    $file_path = get_attached_file($id);

    // 判断是否需要转换存储
    if ($source_storage !== $target_storage) {
        if ($target_storage === 's3') {
            // 如果是从本地或两者转换到 S3
            if ($source_storage === 'local') {
                // 上传到 S3
                $upload_to_s3_result = s3keep_upload_file_to_s3($file_path);

                // 如果上传成功，更新附件的存储为 's3' 并删除本地文件
                if ($upload_to_s3_result) {
                    wp_delete_file($file_path);
                    $thumbnails = s3keeper_get_attachment_thumbnails($id);
                    foreach ($thumbnails as $thumbnail) {
                        s3keep_upload_file_to_s3($thumbnail); // 上传每个缩略图到 S3
                    }
                    foreach ($thumbnails as $thumbnail) {
                        wp_delete_file($thumbnail); // 上传每个缩略图到 S3
                    }
//                    s3keeper_delete_attachment_thumbnails($id);
                    update_post_meta($id, S3KEEP_ATTACHMENT_STORAGE, 's3');
                } else {
                    return false; // 上传失败
                }
            } elseif ($source_storage === 'both') {
                // 从 'both' 转到 's3'
                wp_delete_file($file_path);
                $thumbnails = s3keeper_get_attachment_thumbnails($id);
                foreach ($thumbnails as $thumbnail) {
                    s3keep_upload_file_to_s3($thumbnail); // 上传每个缩略图到 S3
                }
                foreach ($thumbnails as $thumbnail) {
                    wp_delete_file($thumbnail); // 上传每个缩略图到 S3
                }

//                s3keeper_delete_attachment_thumbnails($id);

                update_post_meta($id, S3KEEP_ATTACHMENT_STORAGE, 's3');
            }
        } elseif ($target_storage === 'local') {
            // 如果是从 S3 或两者转换到本地
            if ($source_storage === 's3') {
                // 从 S3 下载文件到本地
                $download_from_s3_result = s3keep_download_file_from_s3($file_path);
                if ($download_from_s3_result) {
                    $thumbnails = s3keeper_get_attachment_thumbnails($id);
                    foreach ($thumbnails as $thumbnail) {
                        s3keep_download_file_from_s3($thumbnail);
                    }
                    update_post_meta($id, S3KEEP_ATTACHMENT_STORAGE, 'local');
                } else {
                    return false; // 下载失败
                }
            }

            // 删除 S3 上的文件
            $delete_from_s3_result = s3keep_delete_file_from_s3($file_path);
            if ($delete_from_s3_result) {

                $thumbnails = s3keeper_get_attachment_thumbnails($id);
                foreach ($thumbnails as $thumbnail) {
                    s3keep_delete_file_from_s3($thumbnail);
                }
                update_post_meta($id, S3KEEP_ATTACHMENT_STORAGE, 'local');
            }
        } elseif ($target_storage === 'both') {
            // 如果目标存储为 'both'
            if ($source_storage === 's3') {
                // 从 S3 下载文件到本地
                $upload_to_local_result = s3keep_download_file_from_s3($file_path);

                // 如果下载成功，更新附件的存储为 'both'
                if ($upload_to_local_result) {
                    $thumbnails = s3keeper_get_attachment_thumbnails($id);
                    foreach ($thumbnails as $thumbnail) {
                        s3keep_download_file_from_s3($thumbnail);
                    }

                    update_post_meta($id, S3KEEP_ATTACHMENT_STORAGE, 'both');
                } else {
                    return false; // 下载失败
                }
            }

            if ($source_storage === 'local') {
                // 上传文件到 S3
                $upload_to_s3_result = s3keep_upload_file_to_s3($file_path);
                if ($upload_to_s3_result) {

                    $thumbnails = s3keeper_get_attachment_thumbnails($id);
                    foreach ($thumbnails as $thumbnail) {
                        s3keep_upload_file_to_s3($thumbnail);
                    }

                    update_post_meta($id, S3KEEP_ATTACHMENT_STORAGE, 'both');
                } else {
                    return false; // 上传失败
                }
            }
        }
    }

    return true; // 转换成功
}

function s3keeper_batch_convert_attachments()
{

    check_ajax_referer('s3keeper_ajax_nonce');

    // 获取源存储、目标存储和附件日期范围
    $source_storage = isset($_POST['source_storage']) ? sanitize_text_field(wp_unslash($_POST['source_storage'])) : 'local';
    $target_storage = isset($_POST['target_storage']) ? sanitize_text_field(wp_unslash($_POST['target_storage'])) : 's3';
    $start_date = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : null;
    $end_date = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : null;
    $allTotal = isset($_POST['allTotal']) ? intval(wp_unslash($_POST['allTotal'])) : -1;
    $page = isset($_POST['page']) ? intval(wp_unslash($_POST['page'])) : 1;
    $batch_size = isset($_POST['batch_size']) ? intval(wp_unslash($_POST['batch_size'])) : 10; // 获取 batch_size，默认为 10

    $query_args = array(
        array(
            'key' => S3KEEP_ATTACHMENT_STORAGE,
            'value' => $source_storage,
            'compare' => '=',
        ));

    if ($source_storage == 'local') {
        $query_args = array(
            'relation' => 'OR', // 使用 OR 关系
            array(
                'key' => S3KEEP_ATTACHMENT_STORAGE,
                'value' => 'local',
                'compare' => '=',
            ),
            array(
                'key' => S3KEEP_ATTACHMENT_STORAGE,
                'compare' => 'NOT EXISTS', // 如果 $source_storage 为 null，检查元数据是否存在
            ),
        );
    }
    // 构建基础的附件查询参数
    $args = array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => $batch_size, // 每次转换 10 个附件
        'paged' => $page,
        'meta_query' => $query_args
    );

    // 如果设置了开始日期
    if ($start_date) {
        $date_query = array('relation' => 'AND'); // 定义关系为AND
        $start_date_obj = DateTimeImmutable::createFromFormat('Y-m-d', $start_date);
        if ($start_date_obj) {
            $date_query[] = array(
                'after' => $start_date_obj->format('Y-m-d 00:00:00'),
                'inclusive' => true // 包含开始日期
            );

        } else {

//            error_log("Failed to parse start date: " . $start_date);
        }

        if ($end_date) {
            $end_date_obj = DateTimeImmutable::createFromFormat('Y-m-d', $end_date);

            if ($end_date_obj) {
                $date_query[] = array(
                    'before' => $end_date_obj->format('Y-m-d 23:59:59'),
                    'inclusive' => true
                );

            } else {

//                error_log("Failed to parse end date: " . $end_date);
            }
        }
        $args['date_query'] = $date_query;
    }

    // 获取匹配的附件
    $attachments_query = new WP_Query($args);

    $total_attachments = $attachments_query->found_posts;

//    echo $total_attachments;exit;
    $converted = 0;

    try {
        // 如果有附件需要处理
        if ($attachments_query->have_posts()) {
            while ($attachments_query->have_posts()) {
                $attachments_query->the_post();
                $attachment_id = get_the_ID();

                // 调用 s3keeper_single_convert 函数进行单个附件的转换
                $conversion_result = s3keeper_single_convert($attachment_id, $source_storage, $target_storage);
                if ($conversion_result) {
                    $converted++;
                }

            }
            wp_reset_postdata();

            // 返回进度到前端
            wp_send_json_success(array(
                'converted' => $converted,
                'total' => $total_attachments,
                'allTotal' => $allTotal,
                'remaining' => ($total_attachments - $converted),
            ));

        } else {

            // 没有找到附件，返回空结果
            wp_send_json_success(array(
                'converted' => 0,
                'total' => 0,
                'allTotal' => $allTotal,
                'remaining' => 0,
            ));
        }
    } catch (ErrorException $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
    }
}


function s3keep_check_s3()
{
    // S3 configuration
    $s3_endpoint = get_option('s3keeper_s3_endpoint');
    $bucket_name = get_option('s3keeper_s3_bucket');
    $aws_key = get_option('s3keeper_s3_key');
    $aws_secret = get_option('s3keeper_s3_secret');
    $aws_path = get_option('s3keeper_s3_path');
    $aws_region = get_option('s3keeper_s3_region', 'us-east-1'); // 添加默认值，如果未设置则为 us-east-1

    // 检查 S3 配置是否完整
    if (empty($s3_endpoint) || empty($bucket_name) || empty($aws_key) || empty($aws_secret)) {
        return [
            'success' => false,
            'message' => 'S3 configuration is incomplete.'
        ];
    }

    // Load AWS SDK
    $autoload_path = S3KEEPER_PLUGIN_DIR . '/vendor/autoload.php';
    if (!file_exists($autoload_path)) {
        return [
            'success' => false,
            'message' => 'AWS SDK autoload file not found at: ' . $autoload_path
        ];
    }
    require $autoload_path;

    if (!class_exists('Aws\S3\S3Client')) {
        return [
            'success' => false,
            'message' => 'AWS SDK could not be loaded or is missing S3Client class.'
        ];
    }

    try {

        $s3_client = new Aws\S3\S3Client([
            'endpoint' => $s3_endpoint,
            'region' => $aws_region,  // 使用配置的region
            'version' => 'latest',
            'credentials' => [
                'key' => $aws_key,
                'secret' => $aws_secret,
            ],
        ]);
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error creating S3 client: ' . $e->getMessage()
        ];
    }

    $path = trim($aws_path, '/');
    try {
        $result = $s3_client->listObjects([
            'Bucket' => $bucket_name,
            'Prefix' => $path  // 添加前缀以列出特定路径下的对象
        ]);
        return [
            'success' => true,
            'message' => 'Successfully connected to S3 and listed objects.'
        ];
    } catch (Aws\Exception\AwsException $e) {
        // Handle S3 API error
//        error_log('Error testing S3 connection: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error connecting to S3: ' . $e->getMessage()
        ];
    } catch (\Exception $e) {
        // Handle other PHP exceptions
//        error_log('Error connecting to S3: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'General error connecting to S3: ' . $e->getMessage()
        ];
    }
}

add_action('restrict_manage_posts', 'add_s3_storage_filter');

function add_s3_storage_filter($post_type)
{
    if ('attachment' !== $post_type) {
        return;
    }
    wp_nonce_field('s3keeper_filter_nonce', 's3keeper_filter_nonce');

    if (isset($_GET['s3keeper_s3_storage'])) {
        // Verify nonce
        if (!isset($_GET['s3keeper_filter_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['s3keeper_filter_nonce'])), 's3keeper_filter_nonce')) {
            return; // Nonce is invalid, stop execution
        }

        // Proceed with sanitizing and using the value
        $selected = sanitize_text_field(wp_unslash($_GET['s3keeper_s3_storage']));
    } else {
        $selected = '';
    }
    ?>
    <select name="s3keeper_s3_storage">
        <option value=""><?php esc_html_e('All Storage', 'keeper-s3'); ?></option>
        <option value="local" <?php selected($selected, 'local'); ?>><?php esc_html_e('Local', 'keeper-s3'); ?></option>
        <option value="s3" <?php selected($selected, 's3'); ?>><?php esc_html_e('S3', 'keeper-s3'); ?></option>
        <option value="both" <?php selected($selected, 'both'); ?>><?php esc_html_e('Both', 'keeper-s3'); ?></option>
    </select>
    <?php
}


//function handle_s3_storage_filter()
//{
//    // Check if the nonce is set and valid
//    if (!isset($_GET['s3keeper_filter_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['s3keeper_filter_nonce'])), 's3keeper_filter_nonce')) {
//        return; // Nonce is invalid, stop execution
//    }
//
//    // Proceed with handling the filter
//    if (isset($_GET['s3keeper_s3_storage'])) {
//        $selected_storage = sanitize_text_field(wp_unslash($_GET['s3keeper_s3_storage']));
//        // Do something with $selected_storage
//    }
//}
//add_action('init', 'handle_s3_storage_filter');

add_filter('pre_get_posts', 's3keeper_filter_attachments_by_s3_storage');

function s3keeper_filter_attachments_by_s3_storage($query)
{
//    check_ajax_referer('s3keeper_ajax_nonce');

    if (!is_admin() || !$query->is_main_query()) {
        return $query;
    }

    if (!isset($_GET['s3keeper_filter_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['s3keeper_filter_nonce'])), 's3keeper_filter_nonce')) {
        return $query; // Nonce is invalid, stop execution
    }


    if (isset($_GET['s3keeper_s3_storage']) && !empty($_GET['s3keeper_s3_storage'])) {
        $s3_storage_filter = sanitize_text_field(wp_unslash($_GET['s3keeper_s3_storage']));

        if ($s3_storage_filter === 'local') {
            // 如果过滤条件是 local，查询 meta_value 为 local 或 meta_key 不存在的记录
            $meta_query = array(
                'relation' => 'OR', // 使用 OR 关系
                array(
                    'key' => S3KEEP_ATTACHMENT_STORAGE,
                    'value' => 'local',
                    'compare' => '=',
                ),
                array(
                    'key' => S3KEEP_ATTACHMENT_STORAGE,
                    'compare' => 'NOT EXISTS', // 查询 meta_key 不存在的记录
                ),
            );
        } else {
            // 其他过滤条件（如 s3），只查询 meta_value 为指定值的记录
            $meta_query = array(
                array(
                    'key' => S3KEEP_ATTACHMENT_STORAGE,
                    'value' => $s3_storage_filter,
                    'compare' => '=',
                ),
            );
        }


        $query->set('meta_query', array($meta_query));


    }

    return $query;
}

add_action('wp_ajax_s3keeper_update_attachment_location', 's3keeper_update_attachment_location');
function s3keeper_update_attachment_location()
{
//    echo 'sdfsdf';exit;
    check_ajax_referer('s3keeper_ajax_nonce');

    if (empty($_POST['post_id']) || empty($_POST['new_location'])) {
        wp_send_json_error(['message' => 'Invalid request']);
        return;
    }

    $post_id = intval($_POST['post_id']);
    $new_location = sanitize_key($_POST['new_location']);

    if (!in_array($new_location, ['local', 's3', 'both'], true)) {
        wp_send_json_error(['message' => 'Invalid storage location']);
        return;
    }

    $old_storage_location = get_post_meta($post_id, S3KEEP_ATTACHMENT_STORAGE, true);
    if (!$old_storage_location) {
        $old_storage_location = 'local';
    }

//    echo $old_storage_location.'|'.$new_location;exit;
    $conversion_result = s3keeper_single_convert($post_id, $old_storage_location, $new_location);

    if ($conversion_result) {
        wp_send_json_success(['message' => 'Attachment storage location updated successfully.']);
    } else {
        wp_send_json_error(['message' => 'Failed to update attachment storage location.']);
    }
}

add_filter('bulk_actions-upload', 's3keeper_add_bulk_actions');
function s3keeper_add_bulk_actions($bulk_actions)
{
    $bulk_actions['s3keeper_bulk_to_s3'] = 'To S3';
    $bulk_actions['s3keeper_bulk_to_local'] = 'To Local';
    $bulk_actions['s3keeper_bulk_to_both'] = 'To Both';
    return $bulk_actions;
}

add_filter('handle_bulk_actions-upload', 's3keeper_handle_bulk_actions', 10, 3);
function s3keeper_handle_bulk_actions($redirect_to, $doaction, $post_ids)
{
    if (strpos($doaction, 's3keeper_bulk_to_') === 0) {
        $new_location = str_replace('s3keeper_bulk_to_', '', $doaction);
        if (!in_array($new_location, ['local', 's3', 'both'], true)) {
            return $redirect_to; // Invalid action
        }

        $updated_count = 0;
        $failed_count = 0;
        $messages = [];

        foreach ($post_ids as $post_id) {
            $old_storage_location = get_post_meta($post_id, S3KEEP_ATTACHMENT_STORAGE, true);
            if (!$old_storage_location) {
                $old_storage_location = 'local';
            }

            $conversion_result = s3keeper_single_convert($post_id, $old_storage_location, $new_location);

            if ($conversion_result) {
                $updated_count++;
            } else {
                $failed_count++;
                $messages[] = sprintf('Failed to update storage location for attachment ID: %d', $post_id);
            }
        }


        $success_message = sprintf(
        // translators: %d is the number of attachments updated.
            _n(
                'Storage location updated for %d attachment.',
                'Storage location updated for %d attachments.',
                $updated_count, 'keeper-s3'
            ),
            $updated_count
        );
        $failed_message = sprintf(
        // translators: %d is the number of attachments updated.
            _n(
                'Failed to update storage location for %d attachment.',
                'Failed to update storage location for %d attachments.',
                $failed_count,
                'keeper-s3'
            ),
            $failed_count
        );


        $redirect_to = add_query_arg('s3keeper_updated', $updated_count, $redirect_to);
        $redirect_to = add_query_arg('s3keeper_failed', $failed_count, $redirect_to);
        $redirect_to = add_query_arg('s3keeper_message', urlencode($success_message), $redirect_to);
        $redirect_to = add_query_arg('s3keeper_fail_message', urlencode(implode('<br>', $messages)), $redirect_to);
        $redirect_to = add_query_arg('s3keeper_fail_message_title', urlencode($failed_message), $redirect_to);
        $redirect_to = add_query_arg('s3keeper_action', $new_location, $redirect_to);
        return $redirect_to;
    }

    return $redirect_to;
}


add_action('admin_notices', 's3keeper_bulk_action_admin_notices');
function s3keeper_bulk_action_admin_notices()
{
    global $pagenow, $post;

    // Check if we are on the attachment edit page
    if ($pagenow === 'post.php' && isset($post) && $post->post_type === 'attachment') {
        $attachment_id = $post->ID;

        // Check if the attachment is stored on S3
        if (s3keeper_is_attachment_stored_on_s3($attachment_id)) {
            echo '<div class="notice notice-warning"><p>This attachment is stored on S3 and cannot be edited directly. Please download the file to your local environment to make changes.</p></div>';
        }
    }

    if (!isset($_GET['s3keeper_filter_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['s3keeper_filter_nonce'])), 's3keeper_filter_nonce')) {
        return; // Nonce is invalid, stop execution
    }

    if (!empty($_GET['s3keeper_updated']) || !empty($_GET['s3keeper_failed'])) {
        $updated_count = isset($_GET['s3keeper_updated']) ? intval(wp_unslash($_GET['s3keeper_updated'])) : 0;
        $failed_count = isset($_GET['s3keeper_failed']) ? intval(wp_unslash($_GET['s3keeper_failed'])) : 0;
        $message = isset($_GET['s3keeper_message']) ? sanitize_text_field(wp_unslash($_GET['s3keeper_message'])) : '';
        $fail_message = isset($_GET['s3keeper_fail_message']) ? sanitize_text_field((wp_unslash($_GET['s3keeper_fail_message']))) : '';
        $fail_message_title = isset($_GET['s3keeper_fail_message_title']) ? sanitize_text_field((wp_unslash($_GET['s3keeper_fail_message_title']))) : '';
        $action = isset($_GET['s3keeper_action']) ? sanitize_text_field(wp_unslash($_GET['s3keeper_action'])) : '';

        $notice_type = $failed_count > 0 ? 'notice-warning' : 'notice-success';
        $notice_message = $message;

        if ($updated_count == 0 && $failed_count == 0) {
            $notice_type = 'notice-error';
            $notice_message = 'No attachments were updated.';
        }
        if ($failed_count > 0) {
            $notice_message = $message . '<br/>' . $fail_message_title . '<br/>' . $fail_message;
        }

        printf(
            '<div class="notice %s is-dismissible"><p>%s</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>',
            esc_attr($notice_type),
            esc_html($notice_message)
        );
    }
}


add_action('admin_enqueue_scripts', 'disable_s3_attachment_edit');
function disable_s3_attachment_edit()
{
    global $pagenow, $post;

    // Check if we are on the attachment edit page
    if ($pagenow === 'post.php' && isset($post) && $post->post_type === 'attachment') {
        $attachment_id = $post->ID;

        // Check if the attachment is stored on S3
        if (s3keeper_is_attachment_stored_on_s3($attachment_id)) {
            wp_localize_script('jquery', 's3_button_text', array(
                'text' => 'Editing Disabled (S3)', // Customize the button text here
            ));

            // Use JavaScript to disable the "Replace File" button
            wp_add_inline_script('jquery', '
                jQuery(document).ready(function($) {
                    // Find the "Edit Image" button and disable it
                    var editImageButton = $(".wp_attachment_image input");
                    if (editImageButton.length) {
                    editImageButton.val(s3_button_text.text);  
//                        editImageButton.hide(); // Hide the button
                        // Alternatively, you can disable the button (but keep it visible):
                         editImageButton.prop("disabled", true).css("opacity", "0.5");
                    }
                });
            ');
        }
    }
}


function s3keeper_enqueue_scripts($admin_page)
{


    wp_enqueue_script('s3keeper-app', S3KEEPER_PLUGIN_URL . '/script.js', array('jquery'), time(), false);

    wp_localize_script('s3keeper-app', 's3keeper_ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('s3keeper_ajax_nonce'),
        'locale' => get_locale()
    ));

    wp_enqueue_style(
        's3keeper-style',
        S3KEEPER_PLUGIN_URL . '/s3keeper.css', // 修改为你的 CSS 文件路径
        array(),
        '1.0.0'
    );

//    echo $admin_page;exit;
    if ($admin_page === 'toplevel_page_keeper-s3') {


        $asset_file = S3KEEPER_PLUGIN_DIR . '/build/index.asset.php';

        if (!file_exists($asset_file)) {
            return;
        }

        $asset = include $asset_file;

        wp_enqueue_script(
            's3keeper-script',
            S3KEEPER_PLUGIN_URL . '/build/index.js',
            $asset['dependencies'],
            $asset['version'],
            array(
                'in_footer' => true,
            )
        );

        $css_handle = is_rtl() ? 's3keeper-style-rtl' : 's3keeper-style';
        $css_file = is_rtl() ? 'build/index-rtl.css' : 'build/index.css';
        wp_enqueue_style(
            $css_handle,
            S3KEEPER_PLUGIN_URL . $css_file,
            array_filter(
                $asset['dependencies'],
                function ($style) {
                    return wp_style_is($style, 'registered');
                }
            ),
            $asset['version'],
        );


    }

}

add_action('admin_enqueue_scripts', 's3keeper_enqueue_scripts');

function s3keeper_admin_menu()
{
    add_menu_page(
        __('KeeperS3', 'keeper-s3'),
        __('KeeperS3', 'keeper-s3'),
        'manage_options',
        'keeper-s3',
        's3keeper_admin_menu_callback',
        'dashicons-edit-large',
    );
}

add_action('admin_menu', 's3keeper_admin_menu');

function s3keeper_admin_menu_callback()
{
    echo '<div id="s3keeper_root"></div>';
}


//add_action('after_setup_theme', 's3keeper_add_custom_thumbnail_sizes');
//
//function s3keeper_add_custom_thumbnail_sizes() {
//    // 添加一个正方形缩略图尺寸
//    add_image_size('square-thumbnail', 60, 60, true);
//
//    // 添加一个宽屏缩略图尺寸
//    add_image_size('wide-thumbnail', 600, 300, true);
//
//    // 添加一个不裁剪的缩略图尺寸
//    add_image_size('uncropped-thumbnail', 400, 400, false);
//}