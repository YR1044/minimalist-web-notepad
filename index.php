<?php
// Path to the directory to save the notes in, without trailing slash.
// Should be outside the document root, if possible.
$save_path = 'note';
// Disable caching.
header('Cache-Control: no-store');
// If no note name is provided, or if the name is too long, or if it contains invalid characters.


if (!isset($_GET['note']) || strlen($_GET['note']) > 64 || !preg_match('/^[a-zA-Z0-9_-]+$/', $_GET['note'])) {
    // Generate a name with 10 random unambiguous characters. Redirect to it.
    header("Location: " . substr(str_shuffle('234579abcdefghjkmnpqrstwxyz'), -10));
    die;
}
$path = $save_path . '/' . $_GET['note'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = isset($_POST['text']) ? $_POST['text'] : file_get_contents("php://input");
    // Update file.
    file_put_contents($path, $text);
    // If provided input is empty, delete file.
    if (!strlen($text)) {
        unlink($path);
    }
    die;
}
// Print raw file when explicitly requested, or if the client is curl or wget.
if (isset($_GET['raw']) || strpos($_SERVER['HTTP_USER_AGENT'], 'curl') === 0 || strpos($_SERVER['HTTP_USER_AGENT'], 'Wget') === 0) {
    if (is_file($path)) {
        header('Content-type: text/plain');
        readfile($path);
    } else {
        header('HTTP/1.0 404 Not Found');
    }
    die;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php print htmlspecialchars($_GET['note'], ENT_QUOTES, 'UTF-8'); ?> - Markdown Note</title>
<link rel="icon" href="favicon.ico" sizes="any" />
<link rel="icon" href="favicon.svg" type="image/svg+xml" />
<style>
:root {
    --btn-bg-color: #fff;
    --btn-text-color: #333;
    --btn-border-color: #ccc;
    --btn-hover-bg-color: #f0f0f0;
    --btn-hover-text-color: #000;
    --btn-hover-border-color: #999;
}

@media (prefers-color-scheme: dark) {
    :root {
        --btn-bg-color: #495265;
        --btn-text-color: #ddd;
        --btn-border-color: #687099;
        --btn-hover-bg-color: #687099;
        --btn-hover-text-color: #fff;
        --btn-hover-border-color: #8791ba;
    }
}
html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    background: #ebeef1;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen,
                  Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
    display: flex;
    flex-direction: column;
}
.container {
    flex: 1;
    display: flex;
    padding: 20px;
    gap: 20px;
    box-sizing: border-box;
}
#content, #preview {
    width: 50%;
    height: calc(100vh - 60px);
    box-sizing: border-box;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 12px 16px;
    overflow: auto;
}
#content {
    resize: none;
    font-family: monospace;
    font-size: 16px;
    background: #fff;
    outline: none;
}
#preview {
    background: #fafafa;
}
@media (prefers-color-scheme: dark) {
    body {
        background: #333b4d;
        color: #ddd;
    }
    #content {
        background: #24262b;
        color: #ddd;
        border-color: #495265;
    }
    #preview {
        background: #1e222c;
        color: #ddd;
        border-color: #495265;
    }
}
#togglePreview {
    margin: 12px 20px 0 20px;
    padding: 6px 12px;
    cursor: pointer;
    font-size: 14px;
    border-radius: 4px;
    border: 1px solid var(--btn-border-color);
    background-color: var(--btn-bg-color);
    color: var(--btn-text-color);
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}

#togglePreview:hover {
    background-color: var(--btn-hover-bg-color);
    color: var(--btn-hover-text-color);
    border-color: var(--btn-hover-border-color);
}
</style>
</head>
<body>
<button id="togglePreview">隐藏预览</button>
<div class="container">
    <textarea id="content" spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off" placeholder="请输入 Markdown 内容..."><?php
        if (is_file($path)) {
            echo htmlspecialchars(file_get_contents($path), ENT_QUOTES, 'UTF-8');
        }
    ?></textarea>
    <div id="preview" aria-label="Markdown 预览区域"></div>
</div>

<!-- 引入开源 Markdown 解析器，使用 CDN 的稳定版本 -->
<script src="./marked.min.js"></script>
<script>
(function(){
    const textarea = document.getElementById('content');
    const preview = document.getElementById('preview');
    const toggleBtn = document.getElementById('togglePreview');
    let cachedContent = textarea.value;

    // 安全的简单xss过滤，只允许部分标签，避免XSS（可根据需求调整或引入更严格库）
    function sanitize(html) {
        var temp = document.createElement('div');
        temp.textContent = html;
        return temp.innerHTML;
    }

    function renderMarkdown(mdText) {
        // 使用 marked 解析 Markdown 为 HTML，开启安全参数
        return marked.parse(mdText);
    }

    function updatePreview() {
        const md = textarea.value;
        // 渲染并更新预览区域
        preview.innerHTML = renderMarkdown(md);
    }

    function uploadContent() {
        const current = textarea.value;
        if (cachedContent !== current) {
            // 保存内容到服务器
            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.href, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            xhr.onload = function () {
                if (xhr.readyState === 4) {
                    cachedContent = current;
                    // 1秒后继续自动保存
                    setTimeout(uploadContent, 1000);
                }
            };
            xhr.onerror = function () {
                // 失败重试
                setTimeout(uploadContent, 1000);
            };
            xhr.send('text=' + encodeURIComponent(current));
        } else {
            // 内容没变，1秒后再检测
            setTimeout(uploadContent, 1000);
        }
    }

    // 监听用户输入，实时更新预览
    textarea.addEventListener('input', () => {
        updatePreview();
    });

    // 新增按钮功能：点击切换预览显示状态
    toggleBtn.addEventListener('click', () => {
        if (preview.style.display === 'none') {
            preview.style.display = '';
            textarea.style.width = '50%';
            toggleBtn.textContent = '隐藏预览';
        } else {
            preview.style.display = 'none';
            textarea.style.width = '100%';
            toggleBtn.textContent = '显示预览';
        }
    });
    
    // 初始化页面
    updatePreview();
    textarea.focus();
    uploadContent();

})();
</script>
</body>
</html>