<?php
use Aws\S3\S3Client;
use Aws\Exception\AwsException;


add_filter('wp_calculate_image_srcset', 's3keeper_replace_srcset_urls_with_s3', 10, 5);

function s3keeper_replace_srcset_urls_with_s3($sources, $size_array, $image_src, $image_meta, $attachment_id)
{
    // 检查附件是否存储在 S3
    $storage = get_post_meta($attachment_id, S3KEEP_ATTACHMENT_STORAGE, true);
    if ($storage === 's3') {
        // 获取 S3 的基本 URL
        $aws_url = get_option('s3keeper_s3_url');


        // 遍历所有 srcset 中的图片
        foreach ($sources as &$source) {
            // 将本地 URL 替换为 S3 的 URL
            $source['url'] = str_replace(wp_upload_dir()['baseurl'], $aws_url, $source['url']);
        }
    }

    return $sources;
}


add_filter('wp_handle_upload', 's3keeper_upload_file_to_s3_or_local', 10, 2);

function s3keeper_upload_file_to_s3_or_local($fileinfo, $context)
{
//    global $wpdb;


    // 获取附件存储位置设置
    $attachment_id = isset($fileinfo['id']) ? $fileinfo['id'] : 0;
    $storage_location = get_post_meta($attachment_id, S3KEEP_ATTACHMENT_STORAGE, true);
    $s3_location = get_option('s3keeper_s3_location');

    if (!$storage_location || $storage_location == '') {
        $storage_location = $s3_location;
    }

    if ($storage_location === 'local') {
        // 如果选择本地存储，保持原来的上传逻辑，文件存储在本地
        return $fileinfo;
    }

    $filepath = $fileinfo['file'];

//    if (wp_attachment_is_image($fileinfo['file'])) {
//    if (strstr($fileinfo['type'], 'image')) {
//        $image_data = wp_getimagesize($filepath);
//
//        $meta = array(
//            'width' => $image_data[0],
//            'height' => $image_data[1],
//
//        );
//
////        echo 1111;
//
//        add_filter('add_attachment', function ($post_id) use ($meta, $storage_location, $filepath) {
////
//            update_post_meta($post_id, '_wp_attachment_metadata', $meta);
//
////            echo 'asdfdsf';exit;
//        }, 10);
//
//    }


    if (s3keep_upload_file_to_s3($filepath)) {

        add_filter('add_attachment', function ($post_id) use ($storage_location) {
            update_post_meta($post_id, S3KEEP_ATTACHMENT_STORAGE, $storage_location);
        }, 10);

        add_filter('wp_generate_attachment_metadata', function ($metadata, $post_id) use ($storage_location, $filepath) {


            if ($storage_location == 's3') {

//                if (isset($metadata['sizes'])) {
//                    $metadata['sizes'] = array();
//                }

                if (file_exists($filepath)) {
                    wp_delete_file($filepath);
//                    s3keeper_delete_attachment_thumbnails($post_id);

                    $thumbnails = s3keeper_get_attachment_thumbnails($post_id);
                    foreach ($thumbnails as $thumbnail) {
                        s3keep_upload_file_to_s3($thumbnail); // 上传每个缩略图到 S3
                    }
                    foreach ($thumbnails as $thumbnail) {
                        wp_delete_file($thumbnail); // 上传每个缩略图到 S3
                    }


                }
            }

            // 删除本地文件，在 WordPress 完成所有对本地文件的操作之后


            return $metadata;

        }, 10, 2);

    }


    return $fileinfo;
}


function s3keeper_get_attachment_thumbnails($attachment_id)
{
    $thumbnails = array();
    $metadata = wp_get_attachment_metadata($attachment_id);

    if ($metadata && isset($metadata['sizes'])) {
        $file_dir = dirname(get_attached_file($attachment_id)); // 获取附件所在的目录
        foreach ($metadata['sizes'] as $size => $size_data) {
            $thumbnails[] = $file_dir . '/' . $size_data['file']; // 构建缩略图的完整路径
        }
    }

    return $thumbnails;
}

function s3keeper_delete_attachment_thumbnails($attachment_id)
{
    $metadata = wp_get_attachment_metadata($attachment_id);
    if (!$metadata || !isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
        return;
    }

    $upload_dir = wp_upload_dir();
    $basedir = $upload_dir['basedir']; // 获取上传目录路径

    foreach ($metadata['sizes'] as $size_data) {
        if (isset($size_data['file'])) {
            $file_path = $basedir . '/' . dirname($metadata['file']) . '/' . $size_data['file'];
            if (file_exists($file_path)) {
                if (!wp_delete_file($file_path)) {
//                    error_log('Failed to delete thumbnail: ' . $file_path);
                }
            }
        }

    }

    // (可选) 如果你希望从元数据中移除缩略图信息
    unset($metadata['sizes']);
    wp_update_attachment_metadata($attachment_id, $metadata);
}


add_filter('wp_get_attachment_url', 'replace_local_url_with_s3', 10, 2);

function replace_local_url_with_s3($url, $attachment_id)
{

    // 获取 S3 配置信息
//    $s3_endpoint = get_option('s3keeper_s3_endpoint');
//    $bucket_name = get_option('s3keeper_s3_bucket');
    $storage_location = get_post_meta($attachment_id, S3KEEP_ATTACHMENT_STORAGE, true);

    // 检查是否有 S3 配置信息
    if (!$storage_location || $storage_location == 'local') {
        return $url; // 如果没有 S3 配置，返回原始 URL
    }

    $upload_dir = wp_upload_dir();
    $upload_base_url = $upload_dir['baseurl'];
    // 获取附件的相对路径，例如 /wp-content/uploads/2024/01/your-image.jpg
    $relative_path = str_replace($upload_base_url, '', $url);

    $aws_url = get_option('s3keeper_s3_url');

    return $aws_url . $relative_path;
//    $home_url = home_url();

//    return str_replace($home_url . '/wp-content/uploads', $aws_url, $url);

//
//
//    if (empty($file_path)) {
//        return $url; // 如果没有文件路径，返回原始 URL
//    }
//
//    // 构建 S3 URL
//    $s3_url = 'https://' . $bucket_name . '.' . $s3_endpoint . '/' . ltrim($file_path, '/');
//
//    return $s3_url; // 返回 S3 URL
}


add_filter('wp_get_attachment_image_src', 's3keeper_replace_thumbnail_url_with_s3', 10, 4);

function s3keeper_replace_thumbnail_url_with_s3($image, $attachment_id, $size, $icon)
{
    // 检查附件是否存储在 S3
    $storage = get_post_meta($attachment_id, S3KEEP_ATTACHMENT_STORAGE, true);
    if ($storage === 's3') {
        // 获取 S3 的基本 URL
        $aws_url = get_option('s3keeper_s3_url') . '/';

        // 获取附件的元数据
        $metadata = wp_get_attachment_metadata($attachment_id);

        if ($metadata && isset($metadata['sizes'])) {
            // 如果 $size 是字符串（例如 'thumbnail', 'medium' 等）
            if (is_string($size) && isset($metadata['sizes'][$size])) {
                // 获取缩略图的文件名
                $thumbnail_file = $metadata['sizes'][$size]['file'];

                // 获取附件的目录
                $file_dir = dirname(get_attached_file($attachment_id));

                // 构建缩略图的本地路径
                $thumbnail_path = $file_dir . '/' . $thumbnail_file;

                // 将本地路径转换为 S3 的 URL
                $upload_dir = wp_upload_dir();
                $relative_path = str_replace($upload_dir['basedir'], '', $thumbnail_path);
                $image[0] = $aws_url . ltrim($relative_path, '/');
            } // 如果 $size 是数组（例如 [width, height]）
            elseif (is_array($size)) {
                // 获取附件的本地路径
                $file_path = get_attached_file($attachment_id);

                // 将本地路径转换为 S3 的 URL
                $upload_dir = wp_upload_dir();
                $relative_path = str_replace($upload_dir['basedir'], '', $file_path);
                $image[0] = $aws_url . ltrim($relative_path, '/');
            }
        }
    }

    return $image;
}


function s3_image_downsize($out, $attachment_id, $size)
{
    // 获取附件路径
//    return $out;
    $storage_location = get_post_meta($attachment_id, S3KEEP_ATTACHMENT_STORAGE, true);

    // 检查是否有 S3 配置信息
    if (!$storage_location || $storage_location == 'local') {
        return $out; // 如果没有 S3 配置，返回原始 URL
    }
    $aws_url = get_option('s3keeper_s3_url');

    $file_path = get_attached_file($attachment_id);
    $file_info = pathinfo($file_path);
    $extension = strtolower($file_info['extension']);
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff'];
    if (!in_array($extension, $image_extensions)) {
        return $out;
    }
    $upload_dir = wp_upload_dir();
    return array($aws_url . str_replace($upload_dir['basedir'], '', $file_path), 60, 60, true);

}

//add_filter('image_downsize', 's3_image_downsize', 10, 4);


function s3keeper_delete_s3_file_on_attachment_delete($return, $post, $force_delete)
{
//    echo 'delete';print_r($post);exit;

    $post_id = $post->ID;

    $storage_location = get_post_meta($post_id, S3KEEP_ATTACHMENT_STORAGE, true);

    // 检查是否有 S3 配置信息
    if (!$storage_location || $storage_location == 'local') {
        return $return;
    }

// 获取附件的文件路径
    $file_path = get_attached_file($post_id);
    // 如果附件存在并且在 S3 上，删除 S3 文件
    if (!s3keep_delete_file_from_s3($file_path)) {
        return false;
    }

    $thumbnails = s3keeper_get_attachment_thumbnails($post_id);
    foreach ($thumbnails as $thumbnail) {
        s3keep_download_file_from_s3($thumbnail);
    }


    return $return;
}

add_action('pre_delete_attachment', 's3keeper_delete_s3_file_on_attachment_delete', 10, 3);


function s3keep_upload_file_to_s3($file_path)
{
    // S3 configuration
    $s3_endpoint = get_option('s3keeper_s3_endpoint');
    $bucket_name = get_option('s3keeper_s3_bucket');
    $aws_key = get_option('s3keeper_s3_key');

    $aws_secret = get_option('s3keeper_s3_secret');
    $aws_path = get_option('s3keeper_s3_path');
    $aws_region = get_option('s3keeper_s3_region','us-east-1');
    $aws_acl = get_option('s3keeper_s3_acl','public-read');

    if (empty($s3_endpoint) || empty($bucket_name) || empty($aws_key) || empty($aws_secret)) {
        return false; // If S3 configuration is missing, return false
    }

    // Load AWS SDK
    require S3KEEPER_PLUGIN_DIR . '/vendor/autoload.php'; // AWS SDK
    $s3_client = new Aws\S3\S3Client([
        'endpoint' => $s3_endpoint,
        'region' => $aws_region,  // Modify as per your region
        'version' => 'latest',
        'credentials' => [
            'key' => $aws_key,
            'secret' => $aws_secret,
        ],
    ]);

    // Prepare the file for upload
//    $key = 'uploads/' . basename($file_path);  // Define the S3 key
//    $s3_key = str_replace(ABSPATH . 'wp-content/uploads', trim($aws_path, '/'), $file_path);
    $upload_dir = wp_upload_dir();
    $key = str_replace($upload_dir['basedir'], trim($aws_path, '/'), $file_path);

    $source_file = $file_path;

    if(!file_exists($source_file))
    {
        throw new ErrorException('File not exists: ' . esc_html($source_file));

    }

    try {
        // Upload file to S3
        $result = $s3_client->putObject([
            'Bucket' => $bucket_name,
            'Key' => $s3_key,
            'SourceFile' => $source_file,
            'ACL' => $aws_acl,  // Adjust the ACL as per your requirements
        ]);

        // Get the URL of the uploaded file
//        $s3_url = $result['ObjectURL'];

        // Optionally, update the attachment with the new S3 URL
//        update_post_meta($attachment_id, 's3_url', $s3_url);

        return true;
    } catch (Exception $e) {
        throw new ErrorException('Error uploading file to S3: ' . esc_html($e->getMessage()));
        // Handle S3 upload errors
//        error_log('Error uploading file to S3: ' . $e->getMessage());
//        return false;
    }
}

function s3keep_download_file_from_s3($file_path)
{
    // S3 configuration
    $s3_endpoint = get_option('s3keeper_s3_endpoint');
    $bucket_name = get_option('s3keeper_s3_bucket');
    $aws_key = get_option('s3keeper_s3_key');
    $aws_path = get_option('s3keeper_s3_path');
    $aws_secret = get_option('s3keeper_s3_secret');
    $aws_region = get_option('s3keeper_s3_region','us-east-1');
    $aws_acl = get_option('s3keeper_s3_acl');

    if (empty($s3_endpoint) || empty($bucket_name) || empty($aws_key) || empty($aws_secret)) {
        return false; // If S3 configuration is missing, return false
    }

    // Load AWS SDK
    require S3KEEPER_PLUGIN_DIR . '/vendor/autoload.php'; // AWS SDK
    $s3_client = new Aws\S3\S3Client([
        'endpoint' => $s3_endpoint,
        'region' => $aws_region,  // Modify as per your region
        'version' => 'latest',
        'credentials' => [
            'key' => $aws_key,
            'secret' => $aws_secret,
        ],
    ]);
    // Define the S3 object key (the path to the file on S3)
//    $key = 'uploads/' . basename($file_path);
//    $key = str_replace(ABSPATH . 'wp-content/uploads', trim($aws_path, '/'), $file_path);
    $upload_dir = wp_upload_dir();
    $key = str_replace($upload_dir['basedir'], trim($aws_path, '/'), $file_path);
//
    // Define the local path to save the file
//    $local_file_path = wp_upload_dir()['path'] . '/' . basename($file_path);
    $local_file_path=$file_path;
    try {
        // Download the file from S3
        $result = $s3_client->getObject([
            'Bucket' => $bucket_name,
            'Key' => $key,
            'SaveAs' => $local_file_path,
        ]);

        // If the file is successfully downloaded, return true
        return file_exists($local_file_path);
    } catch(Exception $e)
    {
        throw new ErrorException('Error: ' . esc_html($e->getMessage()));

    }
}

function s3keep_delete_file_from_s3($file_path)
{
    // S3 configuration
    $s3_endpoint = get_option('s3keeper_s3_endpoint');
    $bucket_name = get_option('s3keeper_s3_bucket');
    $aws_key = get_option('s3keeper_s3_key');
    $aws_path = get_option('s3keeper_s3_path');
    $aws_secret = get_option('s3keeper_s3_secret');

    $aws_region = get_option('s3keeper_s3_region','us-east-1');
    $aws_acl = get_option('s3keeper_s3_acl');

    if (empty($s3_endpoint) || empty($bucket_name) || empty($aws_key) || empty($aws_secret)) {
        return false; // If S3 configuration is missing, return false
    }

    // Load AWS SDK
    require S3KEEPER_PLUGIN_DIR . '/vendor/autoload.php'; // AWS SDK
    $s3_client = new Aws\S3\S3Client([
        'endpoint' => $s3_endpoint,
        'region' => $aws_region,  // Modify as per your region
        'version' => 'latest',
        'credentials' => [
            'key' => $aws_key,
            'secret' => $aws_secret,
        ],
    ]);

    // Define the S3 object key (the path to the file on S3)
//    $key = 'uploads/' . basename($file_path);
//    $key = str_replace(ABSPATH . 'wp-content/uploads', trim($aws_path, '/'), $file_path);

    $upload_dir = wp_upload_dir();
    $key = str_replace($upload_dir['basedir'], trim($aws_path, '/'), $file_path);

    try {
//        echo $key;exit;
        // Delete the file from S3
        $s3_client->deleteObject([
            'Bucket' => $bucket_name,
            'Key' => $key,
        ]);

        return true; // File deleted successfully
    } catch (Exception $e) {
        throw new ErrorException('Error: ' . esc_html($e->getMessage()));

        // Handle error
//        error_log('Error deleting file from S3: ' . $e->getMessage());
//        return false;
    }
}

function s3keeper_is_attachment_stored_on_s3($attachment_id)
{
    $s3_storage = get_post_meta($attachment_id, S3KEEP_ATTACHMENT_STORAGE, true);
    return $s3_storage != 'local'; // If S3KEEP_ATTACHMENT_STORAGE exists and is not empty, the file is stored on S3
}