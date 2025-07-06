# Typecho Moments Plugin 🗨️

[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Typecho](https://img.shields.io/badge/Typecho-1.2+-green.svg)](https://typecho.org)

> 在 Typecho 博客系统中实现类似微博的说说功能，支持API提交、后台管理和前端展示

## 当前版本
V0.1

## 使用方法

- 从 Releases 中下载解压至 `/usr/plugins/`
- 在后台启用并配置，API 令牌必须要配置
- 在要使用的页面上，添加以下代码

```php
<?php \TypechoPlugin\Moments\Plugin::render('#moments', 10, [
    'css' => '',
    'js' => ''
]);?>
```
参数说明：

- #moments: 说说挂载的容器，可选，默认指定`#moments`
- 10: 页面显示的条数，可选，默认为后台插件配置的条数
- []: 可选，指定其他样式和JS的癖好，其实完全没必要，定义后插件默认的样式和JS不会加载

## 说明

基于猫东东的 [Typecho-Plugin-Says](https://github.com/xa1st/Typecho-Plugin-Says) 说说插件，加了话题标签表和关系表，支持话题功能。后台功能懒得重复写代码，就直接走 API 了。Typecho-Plugin-Says 是基于前端解析音乐、卡片等内容的，本插件基于后端解析，所以编辑功能就暂时懒得写了。

## 特别感谢

[猫东东 - https://del.pub](https://del.pub)

## 版权所有
本项目遵循MIT协议，请自由使用。
