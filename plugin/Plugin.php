<?php

namespace TypechoPlugin\Moments;

use Typecho\Plugin\PluginInterface;
use Typecho\Db;
use Typecho\Db\Exception as DbException;
use Typecho\Widget\Helper\Form;
use Typecho\Plugin\Exception as PluginException;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 说说、动态发布
 *
 * @package Moments
 * @author shingchi
 * @version %version%
 * @link https://github.com/shingchi/typecho-plugin-Moments
 */
class Plugin implements PluginInterface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     */
    public static function activate()
    {
        // 创建数据表
        static::createTable();

        // 插件设置页面的js功能
        \Typecho\Plugin::factory('admin/footer.php')->begin = __CLASS__ . '::footer';

        // 添加说说管理面板
        Helper::addPanel(3, 'Moments/admin.php', '动态', '管理你的动态', 'administrator');

        // 添加API路由
        Helper::addRoute('moments', '/api/moments', Widget::class, 'action');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     */
    public static function deactivate()
    {
        // 移除路由
        Helper::removeRoute('moments');
        // 移除面板
        Helper::removePanel(3, 'Moments/admin.php');
        // 删除数据表
        static::dropTable();
    }

    /**
     * 获取插件配置面板
     *
     * @param Form $form 配置面板
     */
    public static function config(Form $form)
    {
        // API 访问令牌
        $token = new Form\Element\Text(
            'token',
            null,
            '',
            _t('API 访问令牌 <span style="color:red">*</span>'),
            _t('用于API接口认证的令牌，建议使用32位以上的随机字符串<br />
            <button type="button" id="btn-generate" class="btn primary">生成 Token</button>
            <button type="button" id="btn-copy" class="btn">复制</button>')
        );
        $token->addRule('required', _t('API访问令牌不能为空'));
        $form->addInput($token);

        // 用户设置
        $db = Db::get();
        $users = $db->fetchAll($db->select('uid', 'screenName')->from('table.users'));
        $options = ['' => _t('- 请选择用户 -')];
        foreach ($users as $user) {
            $options[$user['uid']] = $user['screenName'];
        }
        // 创建用户选择字段
        $userSelect = new Form\Element\Select(
            'userId',
            $options,
            '',
            _t('用户 ID <span style="color:red">*</span>'),
            _t('用于API接口认证和发布说说的用户ID')
        );
        $userSelect->addRule('required', _t('必须选择用户'));
        $form->addInput($userSelect);
        // $userId = new Form\Element\Text('userId', null, '1', _t('用户 ID'), _t('用于API接口认证的用户ID，请填写用户ID，非用户名'));
        // $form->addInput($userId);

        // 页面条目设置
        $pageSize = new Form\Element\Text('pageSize', null, '10', _t('每页说说数量'), _t('用于API接口获取说说列表的每页说说数量'));
        $pageSize->addRule('isInteger', _t('每页说说数量必须是整数'));
        $form->addInput($pageSize);

        // 小尾巴
        $platform = new Form\Element\Textarea(
            'platform',
            NULL,
            _t("绿泡泡 V\\\\1||MicroMessenger\/(.*?)\(\n全能的Windows||Windows\n精致的MacOS||Mac\n肾疼的IPhone||iPhone\n泡面盖子IPad||iPad\n曾经卡死的Android||Android\n真正的神Linux||Linux"),
            _t('来源名称'),
            _t('要识别的小尾巴，格式 [浏览器名称]||[要包含的字符串]，多个小尾巴用换行分隔，例如：Chrome浏览器|Chrome/，优先级从上往下')
        );
        $form->addInput($platform);

        // 默认来源
        $defaultSource = new Form\Element\Text(
            'defaultSource',
            NULL,
            '',
            _t('默认来源'),
            _t('如果所有的都没匹配到，则显示这个默认来源，可选值：直接留空:不显示来源 1:显示操作系统 2:显示浏览器 3:操作系统+浏览器，如果要显示自定义，直接填写自定义内容')
        );
        $form->addInput($defaultSource);

        // 音乐播放器大小
        $musicHeight = new Form\Element\Radio(
            'musicHeight',
            ['1' => _t('是'), '0' => _t('否')],
            0,
            _t('音乐播放器模式'),
            _t('播放器是否启用小模式，默认是播放器高度为52，否则播放器高度为86')
        );
        $form->addInput($musicHeight);

        $musicWidth = new Form\Element\Radio(
            'musicWidth',
            ['1' => _t('是'), '0' => _t('否')],
            0,
            _t('音乐播放器宽度'),
            _t('默认是，为自适应宽度，否则为330或298')
        );
        $form->addInput($musicWidth);

        // 数据选项
        $clear = new Form\Element\Radio(
            'clear',
            ['1' => _t('是'), '0' => _t('否')],
            0,
            _t('清空数据'),
            _t('禁用插件时，是否删除插件产生的所有数据。<br /><strong class="warning">操作不可逆，请谨慎选择！</strong>')
        );
        $form->addInput($clear);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 创建数据表
     *
     * @access private
     * @return void
     */
    private static function createTable()
    {
        $db = Db::get();
        $script = file_get_contents(__TYPECHO_ROOT_DIR__ .
            __TYPECHO_PLUGIN_DIR__ . '/Moments/Mysql.sql');
        $script = str_replace('typecho_', $db->getPrefix(), $script);

        try {
            $script = trim($script);
            $db->query($script, Db::WRITE);
            return _t('数据表已创建, 插件启用成功, 请配置');
        } catch (DbException $e) {
            $code = $e->getCode();
            if (1050 == $code) {
                try {
                    $db->query($db->select()->from('table.memo'));
                    return _t('检测到数据表已存在, 插件启用成功, 请配置');
                } catch (DbException $e) {
                    $code = $e->getCode();
                    throw new PluginException('数据表检测失败, 插件无法启用: ' . $code);
                }
            }
            throw new PluginException('数据表建立失败, 插件无法启用: ' . $code);
        }
    }

    /**
     * 删除数据表
     *
     * @access private
     * @return void
     */
    private static function dropTable()
    {
        // 获取插件配置
        $options = Helper::options()->plugin('Moments');

        if ($options->clear) {
            $db = Db::get();
            $script = 'DROP TABLE `' . $db->getPrefix() . 'moments`;';
            $script .= 'DROP TABLE `' . $db->getPrefix() . 'moments_hashtags`;';
            $script .= 'DROP TABLE `' . $db->getPrefix() . 'moments_relation`;';
            $db->query($script, Db::WRITE);
        }
    }

    /**
     * 插件配置页脚本
     *
     * @access public
     * @return void
     */
    public static function footer()
    {
        $pathInfo = \Typecho\Request::getInstance()->getRequestUri();

        if (strpos($pathInfo, 'options-plugin.php?config=Moments') !== false) {
            echo <<<MOMENTS
            <style>
              .toast {
                position: fixed;
                top: 80px; left : 50%;
                padding: 12px 20px;
                background: #4CAF50;
                color: white;
                border-radius: 4px;
                box-shadow: 0 3px 10px rgba(0,0,0,0.2);
                opacity: 0;
                transform: translate(-50%, -30px);
                transition: all 0.4s ease;
                z-index: 1000;
              }
              .toast.show { opacity: 1; transform: translate(-50%, 0); }
              .toast.error { background: #f44336; }
            </style>
            <script>
              // 安全生成token
              function safeGenerateToken(tokenEl) {
                try {
                  if (isSecureContext() && crypto.randomUUID) {
                    const token = crypto.randomUUID().replace(/-/g, '').substring(0, 32);
                    tokenEl.value = token;
                    showToast('✓ 成功生成 API 令牌');
                    return;
                  }
                } catch (error) {
                  console.error('生成UUID Token失败:', error);
                }

                try {
                  const array = new Uint8Array(16); // 16字节 = 128位
                  crypto.getRandomValues(array);
                  const token = Array.from(array, byte =>
                    byte.toString(16).padStart(2, '0')
                  ).join('');
                  tokenEl.value = token;
                  showToast('✓ 成功生成 API 令牌');
                  return;
                } catch (error) {
                  console.error('生成CryptoRandom Token失败:', error);
                  showToast('❌ 生成 Token 失败，请手动填写', true);
                }
              }

              // 生成token
              document.getElementById('btn-generate').addEventListener('click', function() {
                // 因为自定义id都导致label失效，所以不自定义id改用name来获取
                const tokenEl = document.getElementsByName('token')[0];
                safeGenerateToken(tokenEl);
              });

              // 复制token
              document.getElementById('btn-copy').addEventListener('click', async () => {
                const tokenEl = document.getElementsByName('token')[0];
                await safeCopy(tokenEl);
              });

              // 安全复制
              async function safeCopy(el) {
                // 方法1：现代Clipboard API（HTTPS/localhost）
                if (isSecureContext() && navigator.clipboard?.writeText) {
                  const text = el.value;
                  try {
                    await navigator.clipboard.writeText(text);
                    showToast('✓ 复制成功');
                    return;
                  } catch (error) {
                    console.warn('Clipboard API 失败:', error);
                  }
                }

                // 方法2：兼容方案（所有环境）
                try {
                  // 选中并复制
                  el.select();
                  // 兼容移动设备
                  el.setSelectionRange(0, 99999);
                  const success = document.execCommand('copy');

                  if (success) {
                    showToast('✓ 复制成功');
                  } else {
                    throw new Error('execCommand 复制失败');
                  }
                } catch (error) {
                  console.error('备用方案失败:', error);
                  showToast('❌ 复制失败，请手动复制', true);
                }
              }

              // 显示提示
              function showToast(message, isError = false) {
                const toast = document.createElement('div');
                toast.className = `toast \${isError ? 'error' : ''}`;
                toast.textContent = message;
                document.body.appendChild(toast);

                // 动画显示
                setTimeout(() => toast.classList.add('show'), 10);

                // 3秒后自动移除
                setTimeout(() => {
                  toast.classList.remove('show');
                  setTimeout(() => toast.remove(), 400);
                }, 3000);
              }

              // 检测安全上下文
              function isSecureContext() {
                return window.isSecureContext ||
                       location.protocol === 'https:' ||
                       location.hostname === 'localhost' ||
                       location.hostname === '127.0.0.1';
              }
            </script>

            MOMENTS;
        }
    }

    /**
     * 渲染说说列表
     *
     * @access public
     * @param string $dom 挂载元素id
     * @param int $pageSize 每页数量
     * @param array $config 配置css和js的url
     * @return void
     */
    public static function render($dom = '#moments', $pageSize = NULL, $config = [])
    {
        $pageSize = $pageSize ?? Helper::options()->plugin('Moments')->pageSize;
        $assets = Helper::options()->pluginUrl . '/Moments/assets';
        $css = $config['css'] ?? $assets . '/moments.css?ver=' . time();
        $js = $config['js'] ?? $assets . '/moments.js?ver=' . time();

        echo '<link rel="stylesheet" href="' . $css . '">';
        echo '<script src="' . $js . '"></script>';
        echo <<<MOMENTS
        <script>
            // 初始化应用
            document.addEventListener('DOMContentLoaded', () => {
                window.momentsApp = new MomentsApp('{$dom}', {$pageSize});
                momentsApp.init();
            });
        </script>

        MOMENTS;
    }
}
