<?php

namespace TypechoPlugin\Moments;

use Typecho\Common;
use Typecho\Date;
use Widget\Options;
use Utils\Markdown;

class Utils
{
    /**
     * 解析 Markdown 中的话题标签
     *
     * @param string $content Markdown 格式的内容
     * @return array 解析后的内容和话题列表
     */
    private static function parseMarkdownHashtags($content) {
        // 需保护的 Markdown 内容
        $protected = [];

        // 1. 保护代码块和行内代码
        $content = preg_replace_callback('/(```.*?```|`.*?`)/s', function($matches) use (&$protected) {
            $key = '<!--PROTECTED_' . md5($matches[0]) . '-->';
            $protected[$key] = $matches[0];
            return $key;
        }, $content);

        // 2. 保护 URL
        $content = preg_replace_callback('/(https?:\/\/[^\s]+)/', function($matches) use (&$protected) {
            $key = '<!--PROTECTED_URL_' . md5($matches[0]) . '-->';
            $protected[$key] = $matches[0];
            return $key;
        }, $content);

        // 3. 保护标题
        $content = preg_replace_callback('/^(#{1,6})\s(.*)$/m', function($matches) use (&$protected) {
            $key = '<!--PROTECTED_HEADER_' . md5($matches[0]) . '-->';
            $protected[$key] = $matches[0];
            return $key;
        }, $content);

        // // 4. 保护引用
        // $content = preg_replace_callback('/^>(.*)$/m', function($matches) use (&$protected) {
        //     $key = '<!--PROTECTED_QUOTE_' . md5($matches[0]) . '-->';
        //     $protected[$key] = $matches[0];
        //     return $key;
        // }, $content);

        // // 5. 保护列表
        // $content = preg_replace_callback('/^([-*+]|\d+\.)\s(.*)$/m', function($matches) use (&$protected) {
        //     $key = '<!--PROTECTED_LIST_' . md5($matches[0]) . '-->';
        //     $protected[$key] = $matches[0];
        //     return $key;
        // }, $content);

        // // 6. 保护表格
        // $content = preg_replace_callback('/^(\|.*\|)\s*$/m', function($matches) use (&$protected) {
        //     $key = '<!--PROTECTED_TABLE_' . md5($matches[0]) . '-->';
        //     $protected[$key] = $matches[0];
        //     return $key;
        // }, $content);

        // // 7. 保护换行
        // $content = preg_replace_callback('/\n/', function($matches) use (&$protected) {
        //     $key = '<!--PROTECTED_NEWLINE_' . md5($matches[0]) . '-->';
        //     $protected[$key] = $matches[0];
        //     return $key;
        // }, $content);

        // 4. 解析有效话题标签
        $hashtags = [];
        $content = preg_replace_callback('/(?<!\w)#([^\s#]+)/', function($matches) use (&$hashtags) {
            $tag = trim($matches[1]);
            // 验证话题有效性
            if (!static::isValidHashtag($tag)) {
                return $matches[0];
            }
            // 添加到话题数组（避免重复）
            if (!in_array($tag, $hashtags)) {
                $hashtags[] = $tag;
            }
            // 返回带有链接的HTML
            // return '<a href="?tag=' . urlencode($tag)
            //     . '" data-hashtag="' . $tag . '" class="hashtag">#' . $tag . '</a>';
            // return '<span data-hashtag="' . $tag . '" class="hashtag">#' . $tag . '</span>';
            return '<span data-tag="' . $tag . '" class="hashtag">#' . $tag . '</span>';
        }, $content);

        // 6. 恢复所有保护的内容
        foreach ($protected as $key => $value) {
            $content = str_replace($key, $value, $content);
        }

        return ['content' => $content, 'hashtags' => $hashtags];
    }

    /**
     * 验证话题有效性
     *
     * @param string $tag 话题标签
     * @return bool 有效返回true，无效返回false
     */
    private static function isValidHashtag($tag) {
        // 排除纯数字
        if (preg_match('/^\d+$/', $tag)) return false;

        // 长度限制
        $len = mb_strlen($tag);
        if ($len < 2 || $len > 30) return false;

        // 字符限制
        if (!preg_match('/^[\p{L}\p{N}_-]+$/u', $tag)) return false;

        // 排除常见技术术语
        $excluded = ['php', 'js', 'html', 'css', 'sql', 'json', 'xml'];
        if (in_array(strtolower($tag), $excluded)) return false;

        return true;
    }

    /**
     * 从Markdown内容中提取话题标签
     *
     * @param string $content Markdown格式的内容
     * @return array 话题标签数组
     */
    public static function parseHashtags($content) {
        return static::parseMarkdownHashtags($content)['hashtags'];
    }

    /**
     * 从Markdown内容中提取话题转换后的内容
     *
     * @param string $content Markdown格式的内容
     * @return string 提取后的内容
     */
    public static function convert($content) {
        // 解析话题
        $content = static::parseMarkdownHashtags($content)['content'];
        // 解析扩展内容，豆瓣、网易云音乐、哔哩哔哩等
        $content = static::parseExtensions($content);
        // 解析markdown
        $content = Markdown::convert($content);
        return $content;
    }

    /**
     * 解析扩展内容
     *
     * @param string $content Markdown格式的内容
     * @return string 解析后的内容
     */
    private static function parseExtensions($content) {
        // 5. 解析网易云音乐
        $pattern = '/https?:\/\/music\.163\.com\/(#\/)?(song|program)\/?\?id=(\d+)\/?/i';
        $content = preg_replace_callback($pattern, function($matches) {
            $type = $matches[2] === 'program' ? 3 : 2;
            $songId = $matches[3];
            $musicWidth = Options::alloc()->plugin('Moments')->musicWidth;
            $musicHeight = Options::alloc()->plugin('Moments')->musicHeight;
            // 高度
            if ($musicHeight) {
                $height = 52;
            } else {
                $height = 86;
            }
            $playerHeight = $height - 20;

            // 宽度
            if ($musicWidth) {
                $width = '100%';
            } else {
                if ($musicHeight) {
                    $width = 298;
                } else {
                    $width = 330;
                }
            }
            // 默认小
            // width=298 height=52 &height=32
            // 默认大
            // width=330 height=86 &height=66
            return
                '<div class="netease-music">' .
                '<iframe' .
                    ' src="//music.163.com/outchain/player?type=' . $type . '&id='. $songId .'&auto=0&height=' . $playerHeight . '"' .
                    ' frameborder="no"' .
                    ' border="0"' .
                    ' marginwidth="0"' .
                    ' marginheight="0"' .
                    ' width="' . $width . '"' .
                    ' height="' . $height . '"' .
                    ' referrerpolicy="no-referrer">' .
                '</iframe>' .
                '</div>';
        }, $content);

        // B站视频
        $pattern = '/https?:\/\/(www\.)?bilibili\.com\/video\/((av\d+)|(BV\w+)|([A-Za-z0-9]+))\/?(\?p=\d+)?/i';
        $content = preg_replace_callback($pattern, function($matches) {
            $videoId = $matches[2];
            return '<div class="bilibili-video">' .
                '<iframe' .
                    ' src="//www.bilibili.com/blackboard/html5mobileplayer.html?bvid=' . $videoId . '&as_wide=1&high_quality=1&danmaku=0"' .
                    ' scrolling="no"' .
                    ' border="0"' .
                    ' frameborder="no"' .
                    ' framespacing="0"' .
                    ' allowfullscreen="true">' .
                '</iframe>' .
                '</div>';
        }, $content);

        // 豆瓣电影、书籍、音乐
        // $pattern = '/\[((?:[^|]+)(?:\|[^|]+)*)\]\((https?:\/\/(book|music|movie)\.douban\.com\/subject\/(\d+)\/?)\)/i';
        $pattern = '/\[([^|]*(?:\|[^|]*)+)\]\((https?:\/\/(book|music|movie)\.douban\.com\/subject\/(\d+)\/?)\)/i';
        $content = preg_replace_callback($pattern, function($matches) {
            // 新增捕获组说明：
            // [1] => 方括号完整内容（示例："标题|评分|简介|封面URL|作者"）
            // [2] => 完整URL
            // [3] => 内容类型
            // [4] => 豆瓣ID
            // $metadata[0] 标题
            // $metadata[1] 评分
            // $metadata[2] 简介
            // $metadata[3] 封面URL（可选）
            // $metadata[4] 作者/导演（可选）
            $metadata = explode('|', $matches[1]);
            $info = [
                'id' => $matches[4],
                'type' => $matches[3],
                'title' => $metadata[0],
                'author' => $metadata[4] ?? NULL,
                'rating' => $metadata[1] ?? 0,
                'desc' => $metadata[2] ?? NULL,
                'cover' => $metadata[3] ?? NULL,
                'url' => $matches[2],
            ];

            return static::generateDoubanCard($info);
        }, $content);

        return $content;
    }

    /**
     * 生成豆瓣卡片
     *
     * @param array $info 豆瓣信息
     * @return string 豆瓣卡片HTML
     */
    private static function generateDoubanCard($info) {
        $cover = $info['cover'] ?? 'https://img3.doubanio.com/view/subject/s/public/s' . $info['id'] . '.jpg';
        $author = $info['author'] ? ' – ' . $info['author'] : '';
        $rating = ($info['rating'] && (int) $info['rating'] !== 0) ? $info['rating'] : '暂无评分';
        // 星星直接四舍五入
        $star = floor($info['rating']);
        return '<div class="douban-card">' .
            '<div class="douban-card-cover">' .
            '<img class="douban-card-img loaded1" referrerpolicy="no-referrer" src="' . $cover . '" alt="' . $info['title'] . '">' .
            '</div>' .
            '<div class="douban-card-info">' .
            '<div class="douban-card-title">' .
            '<a href="' . $info['url'] . '" class="cute" target="_blank" rel="noreferrer" title="点击前往豆瓣查看「' . $info['title'] . '」详情">' .
            $info['title'] . '</a>' . $author . '</div>' .
            '<div class="douban-card-rating">' .
            '<span class="rating-star allstar' . $star . ' leading-4"></span>' .
            '<span class="rating-text">' . $rating . '</span>' .
            '</div>' .
            '<div class="douban-card-desc">' . $info['desc'] . '</div>' .
            '</div>' .
            '<div class="douban-card-type ' . $info['type'] . '"></div>' .
            '</div>';
    }

    /**
     * 验证说说内容
     *
     * @param string $content 说说内容
     * @return array [是否有效, 错误消息]
     */
    public static function validateContent($content) {
        // 验证内容
        if (empty($content))  return [false, '说说内容不能为空'];
        // 说说内容不能太长
        if (mb_strlen($content, 'UTF-8') > 1000) return [false, '内容太长（最多1000字符）'];
        // 返回判定结果
        return [true, ''];
    }

    /**
     * 生成UUID
     *
     * @return string 生成的UUID
     */
    public static function generateUUID() {
        return sprintf(
            '%04x%04x%04x%04x%04x%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * 递归过滤XSS
     * @param mixed $data
     * @return mixed
     */
    public static function removeXSS($data) {
        if (is_array($data)) {
            return array_map([__CLASS__, 'removeXSS'], $data);
        }

        if (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->$key = static::removeXSS($value);
            }
            return $data;
        }

        return Common::removeXSS($data);
    }

    /**
     * 过滤公开字段
     *
     * @param array $moment 动态数据
     * @return array 过滤后的动态数据
     */
    public static function filterPublicFields($moment) {
        return [
            'id' => (int) $moment['id'],
            'uuid' => $moment['uuid'],
            'content' => static::convert($moment['content']),
            'created' => (new Date($moment['created']))->word(),
            'from' => static::getPlatform($moment['agent']),
            'pinned' => (int) $moment['pinned'],
        ];
    }

    /**
     * 获取平台信息
     *
     * @param string $userAgent 浏览器UA字符串
     * @return string 平台信息
     */
    public static function getPlatform($userAgent = '') {
        $result = static::getSource($userAgent);
        if (!empty($result)) return $result;

        // 获取默认来源配置
        $defaultSource = Options::alloc()->plugin('Moments')->defaultSource;

        // 根据配置处理来源信息
        if (empty($defaultSource)) return '';
        if ($defaultSource == '1') return static::getOS($userAgent);
        if ($defaultSource == '2') return static::getBrowser($userAgent);
        if ($defaultSource == '3') return static::getOS($userAgent) . (static::getBrowser($userAgent) ? ' ' . static::getBrowser($userAgent) : '');

        // 返回自定义内容
        return $defaultSource;
    }

    /**
     * 获取来源信息
     *
     * @param string $source 来源字符串
     * @param string $rule 规则字符串
     * @return string 来源信息
     */
    public static function getSource($source = '', $rule = '') {
        // 如果没有，直接返回未知
        if (empty($source))  return '';
        // 获取规则字符串，取消转义
        if (empty($rule)) $rule = html_entity_decode(Options::alloc()->plugin('Moments')->platform);
        // 将规则字符串按行分割
        $ruleLines = explode("\n", $rule);
        // 按行拆分
        foreach ($ruleLines as $line) {
            // 去除行首尾空白
            $line = trim($line);
            // 跳过空行
            if (empty($line)) continue;
            // 提取名称和规则部分
            if (preg_match('/^(.*?)\|\|(.*?)/U', $line, $ruleMatches)) {
                // 默认名称
                $defaultName = $ruleMatches[1];
                // 正则表达式内容
                $pattern = $ruleMatches[2];
                // 修复正则表达式中的\\d等转义字符（假设d+实际上应该是\\d+）
                // $pattern = str_replace('d+', '\\d+', $pattern);
                //$pattern = str_replace('s', '\\s', $pattern);
                // 使用正则表达式匹配源字符串
                if (preg_match('/' . $pattern . '/i', $source, $matches)) {
                    // 核心替换逻辑：动态解析 \\数字 并映射到 $matches
                    $defaultName = preg_replace_callback(
                        '/\\\\\\\\\d+/',  // 匹配 \\数字（如 \\1, \\2）
                        function($m) use ($matches) {
                            $index = (int)substr($m[0], 2);  // 提取数字部分
                            return $matches[$index] ?? $m[0]; // 存在则替换，否则保留原文本
                        },
                        $defaultName
                    );
                    return $defaultName; // 返回处理后的值
                }
            }
        }
        // 如果没有匹配到任何规则，返回未知
        return '';
    }

    /**
     * 获取操作系统信息
     *
     * @param string $userAgent 浏览器UA字符串
     * @return string 操作系统信息
     */
    public static function getOS($userAgent = '') {
        // 返回值
        $result = ['os' => 'Unknown', 'browser' => 'Unknown'];
        // 操作系统检测 - 用一个正则匹配所有可能的操作系统
        if (preg_match('/(?:Windows NT (\d+\.\d+)|iPhone|iPad|Macintosh|Mac OS X|Android|Linux)/i', $userAgent, $osMatches)) {
            switch (true) {
                case isset($osMatches[1]):
                    // Windows 版本映射
                    $winVersions = [
                        '10.0' => 'Windows 10/11',
                        '6.3' => 'Windows 8.1',
                        '6.2' => 'Windows 8',
                        '6.1' => 'Windows 7',
                        '6.0' => 'Windows Vista',
                        '5.1' => 'Windows XP'
                    ];
                    $result['os'] = $winVersions[$osMatches[1]] ?? 'Windows';
                    break;
                case stripos($osMatches[0], 'iPhone') !== false:
                    $result['os'] = 'iOS';
                    break;
                case stripos($osMatches[0], 'iPad') !== false:
                    $result['os'] = 'iPadOS';
                    break;
                case stripos($osMatches[0], 'Mac') !== false:
                    $result['os'] = 'macOS';
                    break;
                case stripos($osMatches[0], 'Android') !== false:
                    $result['os'] = 'Android';
                    break;
                case stripos($osMatches[0], 'Linux') !== false:
                    $result['os'] = 'Linux';
                    break;
            }
        }
        return $result['os'];
    }

    /**
     * 获取浏览器信息
     *
     * @param string $userAgent 浏览器UA字符串
     * @return string 浏览器信息
     */
    public static function getBrowser($userAgent  = '') {
        // 浏览器检测 - 用一个正则匹配所有主流浏览器及版本
        $pattern = '/(Edg|Edge)\/(\d+)\.|Chrome\/(\d+)\.|Firefox\/(\d+)\.|Version\/(\d+)\..*Safari|MSIE (\d+)\.|rv:(\d+)\..*Trident/i';
        if (preg_match($pattern, $userAgent, $browserMatches)) {
            if (!empty($browserMatches[1]) && !empty($browserMatches[2])) {
                // Edge
                $result['browser'] = 'Microsoft Edge ' . $browserMatches[2];
            } else if (!empty($browserMatches[3])) {
                // Chrome
                $result['browser'] = 'Google Chrome ' . $browserMatches[3];
            } else if (!empty($browserMatches[4])) {
                // Firefox
                $result['browser'] = 'Mozilla Firefox ' . $browserMatches[4];
            } else if (!empty($browserMatches[5])) {
                // Safari
                $result['browser'] = 'Apple Safari ' . $browserMatches[5];
            } else if (!empty($browserMatches[6])) {
                // IE (MSIE)
                $result['browser'] = 'IE ' . $browserMatches[6];
            } else if (!empty($browserMatches[7])) {
                // IE (Trident)
                $result['browser'] = 'IE ' . $browserMatches[7];
            }
        }
        return $result['browser'];
    }
}
