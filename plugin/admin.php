<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$options = Widget\Options::alloc()->plugin('Moments');
?>

<main class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2><?php _e('说说管理'); ?></h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2">
                <div class="col-mb-12 typecho-post-area">
                    <h3><?php _e('发布新说说'); ?></h3>
                    <div class="clearfix">
                        <label for="content" class="sr-only">说说内容</label>
                        <textarea
                            name="content"
                            id="content"
                            style="height: 150px"
                            autocomplete="off"
                            class="w-100 mono pastable"
                            placeholder="内容支持markdown语法，还支持一些特殊的标记，比如:
网易云音乐 / B站视频: 直接将链接复制进来
示例: https://www.bilibili.com/video/BV1u1jsz2E6f
豆瓣卡片格式: [标题|评分|简介|封面图(可选)|作者(可选)](豆瓣链接)
示例: [七日世界|5.2|简介|//img1.doubanio.com/lpic/s36104107.jpg](https://book.douban.com/subject/36104107)"
                        ></textarea>
                        <p class="submit clearfix">
                            <span class="right">
                                <button type="button" class="btn primary" id="btn-submit">发布</button>
                            </span>
                        </p>
                    </div>
                </div>
                <div class="col-mb-12 typecho-option">
                    <h3><?php _e('我的说说'); ?></h3>
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <tbody class="moments"></tbody>
                        </table>
                        <div class="typecho-pager" style="float:unset">
                            <ul class="pagination" id="pagination">
                                <!-- 分页将由JavaScript动态生成 -->
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
?>
<link rel="stylesheet" href="<?php echo Widget\Options::alloc()->pluginUrl; ?>/Moments/assets/moments.css">
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

    .typecho-pager {
        border-top: 1px solid #f0f0ec;
    }
    .pagination {
        padding-left: 0;
    }
</style>
<script>
    const token = '<?php echo $options->token; ?>';
    const pageSize = <?php echo $options->pageSize; ?>;
    // const btnSubmit = document.getElementById('btn-submit');

    // 发布说说
    async function publish() {
        try {
            let url = `/api/moments`;
            let contentEl = document.getElementById('content');
            let content = contentEl.value.trim();

            if (content.length === 0) {
                showToast('❌ 请输入内容');
                return;
            }

            const btnSubmit = document.getElementById('btn-submit');
            btnSubmit.innerHTML = '发布中...';

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    content: content
                }),
            });
            const ret = await response.json();
            const data = ret.data.items || [];

            if (data.length > 0) {
                contentEl.value = '';
                btnSubmit.innerHTML = '发布';
                loadPage();
                showToast('✓ 发布成功');
            } else {
                btnSubmit.innerHTML = '发布';
                // throw new Error(ret.message || '发布失败');
                showToast(ret.message || '❌ 发布失败', true);
            }
        } catch (error) {
            console.error('发布失败: ', error.message);
        }

        // fetch('/api/moments', {
        //     method: 'POST',
        //     headers: {
        //         'Authorization': `Bearer ${token}`,
        //         'Content-Type': 'application/json'
        //     },
        //     body: JSON.stringify({
        //         content: contentEl.value
        //     })
        // }).then(function(response) {
        //     document.getElementById('btn-submit').innerHTML = '发布';
        //     if (response.status === 200 && response.data.items.length > 0) {
        //         contentEl.value = '';
        //         alert('发布成功');
        //     } else {
        //         alert('发布失败');
        //     }
        // });
    }

    /**
     * 删除说说
     * @param {number} momentId 说说ID
     */
    async function deleteMoment(momentId) {
        if (!confirm('确定要删除吗？')) {
            return;
        }

        try {
            const response = await fetch('/api/moments', {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: [momentId]
                })
            });
            const ret = await response.json();
            if (ret.status === 1) {
                showToast('✓ 删除成功');
                // 删除当前tr
                document.getElementById(`moment-${momentId}`).remove();
            } else {
                showToast(ret.message || '❌ 删除失败', true);
            }
        } catch (error) {
            console.error('删除失败: ', error.message);
        }
    }

    /**
     * 加载说说列表
     * @param {number} page 页码
     */
    async function loadPage(page = 1) {
        try {
            let url = `/api/moments?pageSize=${pageSize}&page=${page}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });
            const ret = await response.json();
            const data = ret.data.items || [];
            if (data.length > 0) {
                renderMomentsList(data);
                renderPagination(ret.data.pagination);
            } else {
                showToast(ret.message || '❌ 加载失败', true);
            }
        } catch (error) {
            console.error('加载失败: ', error.message);
        }
    }

    /**
     * 渲染说说列表
     * @param {Moment[]} moments 说说列表
     */
    function renderMomentsList(moments) {
        const momentsEl = document.querySelector('.moments');
        momentsEl.innerHTML = '';
        moments.forEach(item => {
            momentsEl.innerHTML += `
            <tr id="moment-${item.id}" data-moment='${JSON.stringify(item)}'>
                <td valign="top" class="comment-body">
                    <div class="comment-date">${item.created}，来自 ${item.from}</div>
                    <div class="comment-content">${item.content}</div>
                    <div class="comment-action">
                        <a href="#" data-moment-id="${item.id}" class="operate-edit">编辑</a>
                        <a href="#" data-moment-id="${item.id}" class="operate-delete">删除</a>
                    </div>
                </td>
            </tr>
            `;
        });
    }

    /**
     * 生成分页组件
     * @param {Pagination} paginationData 分页数据
     */
    function renderPagination(paginationData) {
        const pagination = document.getElementById('pagination');
        pagination.innerHTML = '';

        const { totalPages, page } = paginationData;

        // 添加上一页按钮
        const prevItem = document.createElement('li');
        prevItem.className = 'prev';
        const prevLink = document.createElement('a');
        prevLink.href = '#';
        prevLink.innerHTML = '&laquo; 上一页';
        if (page !== 1) {
            prevItem.appendChild(prevLink);
            pagination.appendChild(prevItem);
            prevLink.dataset.page = page - 1;
            prevLink.onclick = (e) => {e.preventDefault(); loadPage(page - 1)};
        }

        // 计算页码显示逻辑
        let startPage, endPage;
        if (totalPages <= 5) {
            // 显示所有页码
            startPage = 1;
            endPage = totalPages;
        } else {
            // 计算起始和结束页码
            if (page <= 3) {
                startPage = 1;
                endPage = 5;
            } else if (page + 2 >= totalPages) {
                startPage = totalPages - 4;
                endPage = totalPages;
            } else {
                startPage = page - 2;
                endPage = page + 2;
            }
        }

        // 添加页码
        for (let i = startPage; i <= endPage; i++) {
            const pageItem = document.createElement('li');
            pageItem.className = `${i === page ? 'current' : ''}`;
            const pageLink = document.createElement('a');
            pageLink.href = '#';
            pageLink.textContent = i;
            pageLink.dataset.page = i;
            pageLink.onclick = (e) => {e.preventDefault(); loadPage(i)};
            // pageLink.onclick = () => changePage(i);
            pageItem.appendChild(pageLink);
            pagination.appendChild(pageItem);
        }

        // 添加下一页按钮
        const nextItem = document.createElement('li');
        nextItem.className = 'next';
        const nextLink = document.createElement('a');
        // nextLink.className = 'page-link next';
        nextLink.href = '#';
        nextLink.innerHTML = '下一页 &raquo;';
        if (page !== totalPages) {
            nextItem.appendChild(nextLink);
            pagination.appendChild(nextItem);
            nextLink.dataset.page = page + 1;
            nextLink.onclick = (e) => {e.preventDefault(); loadPage(page + 1)};
            // nextLink.onclick = () => changePage(page + 1);
        }
    }

    /**
     * 提示框
     * @param {string} message 提示内容
     * @param {boolean} isError 是否错误提示
     */
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

    // 加载监听
    document.addEventListener("DOMContentLoaded", function() {
        loadPage();
    });

    // 点击监听
    document.addEventListener('click', function(e) {
        // 发布监听
        if (e.target.id === 'btn-submit') {
            publish();
        }

        // 删除操作监听
        if (e.target.classList.contains('operate-delete')) {
            e.preventDefault();
            // const memo = e.target.closest('tr').dataset.memo;
            // deleteMemo(JSON.parse(memo));
            const momentId = e.target.dataset.momentId;
            deleteMoment(momentId);
        }

        // 编辑操作监听
        if (e.target.classList.contains('operate-edit')) {
            e.preventDefault();
            showToast('我还懒得写啊！');
            // const moment = e.target.closest('tr').dataset.moment;
            // editMoment(JSON.parse(moment));
        }
    });
</script>

<?php
include 'footer.php';
?>
