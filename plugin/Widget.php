<?php

namespace TypechoPlugin\Moments;

use Typecho\Db;
use Typecho\Widget as TypechoWidget;
use Typecho\Widget\Exception;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Widget extends TypechoWidget
{
    /**
     * 插件数据库
     *
     * @var Db
     */
    private $db;

    /**
     * 插件配置
     *
     * @var Options
     */
    private $options;

    /**
     * 构造函数
     *
     * @access public
     * @param mixed $request request对象
     * @param mixed $response response对象
     * @param mixed $params 参数列表
     */
    public function __construct($request, $response, $params = null)
    {
        parent::__construct($request, $response, $params);
        // 可做其他初始设置
        $this->db = Db::get();
        $this->options = Options::alloc()->plugin('Moments');
    }

    /**
     * 组件默认执行方法
     *
     * @access public
     * @return void
     */
    public function execute()
    {
    }

    /**
     * 发布说说
     *
     * @access public
     * @return void
     */
    public function publish()
    {
        // 身份认证
        $this->checkAuthToken();

        // uid content created updated status uuid ip agent pinned source
        try {
            // $moments = $this->request->get('@json');
            $moment = $this->request->get('@json', []);
            $moment = Utils::removeXSS($moment);
            $moment['content'] = isset($moment['content']) ? trim($moment['content']) : '';

            // 内容验证
            list($valid, $message) = Utils::validateContent($moment['content']);
            // 内容验证失败直接抛出错误
            if (!$valid) return $this->response->throwJson(['status' => 0, 'message' => $message]);

            // 真实的内容id
            $realId = 0;

            $currentTime = time();
            $moment['created'] = $moment['created'] ?? $currentTime;
            $moment['updated'] = $currentTime;
            $moment['status'] = $moment['status'] ?? 1;
            $moment['uid'] = $moment['uid'] ?? $this->options->userId;
            $moment['uuid'] = $moment['uuid'] ?? Utils::generateUUID();
            $moment['ip'] = $this->request->getIp() ?? '0.0.0.0';
            $moment['agent'] = $this->request->getAgent();
            $moment['pinned'] = $moment['pinned'] ?? 0;

            if ($moment['id'] && $moment['id'] > 0) {
                // 更新说说
                $updateRows = $this->db->query($this->db->update('table.moments')->where('id = ?', $moment['id'])->rows($moment));
                if ($updateRows) {
                    $realId = $moment['id'];
                }
            } else {
                // 新增说说
                $realId = $this->db->query($this->db->insert('table.moments')->rows($moment));
            }

            if ($realId > 0) {
                $moment['id'] = $realId;

                /** 更新话题标签 */
                // 解析话题
                $hashtags = Utils::parseHashtags($moment['content']);

                if (!empty($hashtags)) {
                    $this->setTags($realId, $hashtags);
                }

                // $data = [$moment];
                $this->response->throwJson([
                    'status' => 1,
                    'message' => 'success',
                    'data' => [
                        // 'items' => [$moment]
                        'items' => [Utils::filterPublicFields($moment)]
                    ]
                ]);
            } else {
                $this->response->throwJson(['status' => 0, 'message' => 'error']);
            }
        } catch (Exception $e) {
            $this->response->throwJson(['status' => 0, 'message' => 'Internal server error: ' . $e->getMessage()]);
        }
    }

    /**
     * 获取说说列表
     *
     * @access public
     * @return void
     */
    public function fetch()
    {
        try {
            // 去掉body方式
            // $params = $this->request->get('@json', []);
            // $params = Utils::removeXSS($params);

            // $pageSize = $params['pageSize'] ?? ($this->options->pageSize ?? 10);
            // $page = $params['page'] ?? 1;
            // $offset = ($page - 1) * $pageSize;

            $defaultPageSize = $this->options->pageSize ?? 15;
            $pageSize = $this->request->filter('int')->get('pageSize', $defaultPageSize);
            $page = $this->request->filter('int')->get('page', 1);
            $tag = $this->request->filter('xss')->get('tag');

            // 构建基础查询
            $select = $this->db->select('table.moments.*')
                ->from('table.moments')->where('table.moments.status = ?', 1);

            // 构建话题查询条件
            if (null != $tag) {
                $select->join('table.moments_relation', 'table.moments.id = table.moments_relation.moment_id')
                    ->join('table.moments_hashtags', 'table.moments_relation.tag_id = table.moments_hashtags.id')
                    ->where('table.moments_hashtags.name = ?', $tag);
            }

            // 获取总数（在LIMIT、OFFSET和ORDER BY之前）
            $countSql = clone $select;
            $total = $this->db->fetchObject($countSql->select(['COUNT(DISTINCT table.moments.id)' => 'num']))->num;

            // 执行分页查询
            $select->order('table.moments.created', Db::SORT_DESC)
                // ->offset($offset)->limit($pageSize);
                // $offset = ($page - 1) * $pageSize 不要这句时，下面的等同上面
                ->page($page, $pageSize);

            $result = (array) $this->db->fetchAll($select);
            // $items = array_map(function ($item) {
            //     // 处理内容
            //     $item['content'] = Utils::convert($item['content']);
            //     $item['from'] = Utils::getPlatform($item['agent']);
            //     unset($item['agent'], $item['source'], $item['ip'], $item['status']);
            //     return $item;
            // }, $result);
            $items = array_map('\TypechoPlugin\Moments\Utils::filterPublicFields', $result);

            $response = [
                'status' => 1,
                'message' => 'success',
                'data' => [
                    'items' => $items,
                    'pagination' => [
                        'totalPages' => ceil($total / $pageSize),
                        'page' => $page,
                        'pageSize' => $pageSize
                    ]
                ]
            ];

            $this->response->throwJson($response);
        } catch (Exception $e) {
            $this->response->throwJson([
                'status' => 0,
                'message' => 'Internal server error: ' . $e->getMessage()
            ]);
        }

    }

    /**
     * 组件实现方法
     *
     * @access public
     * @return void
     */
    public function delete()
    {
        try {
            $moments = $this->request->get('@json', []);

            if (empty($moments['id'])) {
                $this->response->throwJson(['status' => 0, 'message' => 'Missing parameter: id']);
            }

            $moments = Utils::removeXSS($moments['id']);
            $deleteCount = 0;

            foreach ($moments as $momentId) {
                $resource = $this->db->query($this->db->delete('table.moments')->where('id = ?', $momentId));

                if ($resource) {
                    // 删除标签
                    $this->setTags($momentId, null);
                    $deleteCount++;
                }
            }

            // 清理没有任何内容的标签
            if ($deleteCount > 0) {
                $this->clearTags();
            }

            $this->response->throwJson([
                'status' => 1,
                'message' => $deleteCount . '条说说删除成功',
            ]);
        } catch (Exception $e) {
            $this->response->throwJson([
                'status' => 0,
                'message' => 'Internal server error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 设置内容标签
     *
     * @param integer $momentId 动态ID
     * @param array $tags 标签数组
     * @return void
     */
    private function setTags($momentId, $tags)
    {
        $tags = $tags ?? [];

        /** 取出已有tag */
        $existTags = array_column(
            $this->db->fetchAll(
                $this->db->select('table.moments_hashtags.id')
                    ->from('table.moments_hashtags')
                    ->join('table.moments_relation', 'table.moments_relation.tag_id = table.moments_hashtags.id')
                    ->where('table.moments_relation.moment_id = ?', $momentId)
            ),
            'id'
        );

        /** 删除已有tag */
        if ($existTags) {
            foreach ($existTags as $tag) {
                if (0 == strlen($tag)) {
                    continue;
                }

                $this->db->query($this->db->delete('table.moments_relation')
                    ->where('moment_id = ?', $momentId)
                    ->where('tag_id = ?', $tag));

                // 更新统计
                $this->db->query($this->db->update('table.moments_hashtags')
                    ->expression('count', 'count - 1')
                    ->where('id = ?', $tag));
            }
        }

        /** 取出插入tag */
        $insertTags = $this->scanTags($tags);

        /** 插入tag */
        if ($insertTags) {
            foreach ($insertTags as $tag) {
                if (0 == strlen($tag)) {
                    continue;
                }

                $this->db->query($this->db->insert('table.moments_relation')
                    ->rows([
                        'tag_id' => $tag,
                        'moment_id' => $momentId
                    ]));

                $this->db->query($this->db->update('table.moments_hashtags')
                    ->expression('count', 'count + 1')
                    ->where('id = ?', $tag));
            }
        }
    }

    /**
     * 根据tag获取ID
     *
     * @param array $tags 标签数组
     * @return void
     */
    private function scanTags($inputTags)
    {
        $tags = is_array($inputTags) ? $inputTags : [$inputTags];
        $result = [];

        foreach ($tags as $tag) {
            if (empty($tag)) {
                continue;
            }

            // 检查标签是否存在
            $row = $this->db->fetchRow($this->db->select()
                ->from('table.moments_hashtags')
                ->where('name = ?', $tag)->limit(1));

            if ($row) {
                $result[] = $row['id'];
            } else {
                // 新增标签
                $result[] = $this->db->query($this->db->insert('table.moments_hashtags')->rows([
                    'name'  => trim($tag),
                    'count' => 0
                ]));
            }
        }

        return is_array($inputTags) ? $result : current($result);
    }

    /**
     * 清理没有任何内容的标签
     *
     * @return void
     */
    private function clearTags()
    {
        // 取出count为0的标签
        $tags = array_column($this->db->fetchAll($this->db->select('id')
            ->from('table.moments_hashtags')->where('count = ?', 0)), 'id');

        foreach ($tags as $tag) {
            // 确认是否已经没有关联了
            $content = $this->db->fetchRow($this->db->select('moment_id')
                ->from('table.moments_relation')->where('tag_id = ?', $tag)
                ->limit(1));

            if (empty($content)) {
                $this->db->query($this->db->delete('table.moments_hashtags')
                    ->where('id = ?', $tag));
            }
        }
    }

    /**
     * 检查认证令牌
     *
     * @return bool 认证成功返回true，失败返回false
     */
    private function checkAuthToken() {
        $authorization = $this->request->getHeader('Authorization');
        $token = $authorization ? str_replace('Bearer ', '', $authorization) : '';
        if (empty($token) || ($token !== $this->options->token)) {
            $this->response->throwJson(['status' => 0, 'message' => 'Unauthorized']);
        }
    }

    /**
     * 绑定动作
     */
    public function action()
    {
        // 绑定请求
        $this->on($this->request->isGet())->fetch();
        $this->on($this->request->isPost())->publish();
        $this->on($this->request->isPut())->publish();
        $this->on('DELETE' == $this->request->getServer('REQUEST_METHOD'))->delete();
    }
}
