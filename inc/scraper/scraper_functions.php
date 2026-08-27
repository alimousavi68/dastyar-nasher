<?php
// Function to get the base URL of the current site
function complete_url($url, $base_url)
{
    // چک می‌کند که آیا URL شامل پروتکل است یا خیر (http:// یا https://)
    if (parse_url($url, PHP_URL_SCHEME) === null) {
        // اگر URL با '/' شروع شود، به ابتدای آن base_url را اضافه می‌کند
        if ($url[0] === '/') {
            return rtrim($base_url, '/') . $url;
        } else {
            return rtrim($base_url, '/') . '/' . ltrim($url, '/');
        }
    }
    // اگر URL شامل پروتکل باشد، همان URL را برمی‌گرداند
    return $url;
}

// Clear Not Allowed Tags
function clear_not_allowed_tags($html, $base_url)
{
    // ایجاد یک شیء DOMDocument
    $dom = new DOMDocument();

    // بارگیری HTML بدون عنوان
    libxml_use_internal_errors(true); // غیرفعال کردن پیام‌های خطا
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); // پاک کردن خطاها

    // حذف تگ‌های <a> و <h1>
    $tagsToRemove = array('a', 'h1', 'strong');
    foreach ($tagsToRemove as $tag) {
        $elementsToRemove = $dom->getElementsByTagName($tag);
        $elements = [];
        foreach ($elementsToRemove as $element) {
            $elements[] = $element; // Save elements to array for safe removal
        }
        foreach ($elements as $element) {
            $text = $element->nodeValue;
            $textNode = $dom->createTextNode($text);
            $element->parentNode->replaceChild($textNode, $element);
        }
    }

    // حذف ویژگی‌های id، class و style از تمام تگ‌ها
    $allElements = $dom->getElementsByTagName('*');
    foreach ($allElements as $element) {
        $element->removeAttribute('id');
        $element->removeAttribute('class');
        $element->removeAttribute('style');
        $element->removeAttribute('href');
        $element->removeAttribute('title');
    }

    // بررسی و اصلاح src تگ‌های img
    $imgTags = $dom->getElementsByTagName('img');
    foreach ($imgTags as $img) {
        $src = $img->getAttribute('src');
        $complete_src = complete_url($src, $base_url);
        $img->setAttribute('src', $complete_src);
    }

    // دریافت HTML نهایی
    $filteredHtml = $dom->saveHTML();

    // حذف <?xml encoding="UTF-8">
    $filteredHtml = substr($filteredHtml, strlen('<?xml encoding="UTF-8">'));

    // بازگرداندن HTML نهایی
    return $filteredHtml;
}


// Check Post Link Status
function check_post_link_status($encoded_url)
{
    // مقداردهی اولیه cURL
    $ch = curl_init();

    // تنظیمات cURL
    curl_setopt($ch, CURLOPT_URL, $encoded_url); // آدرس URL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // بازگشت پاسخ به‌جای نمایش مستقیم
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // دنبال کردن ریدایرکت‌ها
    curl_setopt($ch, CURLOPT_HEADER, true); // بازگشت هدرها
    curl_setopt($ch, CURLOPT_NOBODY, false); // واکشی محتوای صفحه

    // اجرای درخواست
    $content = curl_exec($ch);

    // دریافت اطلاعات مربوط به URL نهایی و وضعیت HTTP
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); // URL نهایی پس از دنبال کردن ریدایرکت‌ها

    // بستن cURL
    curl_close($ch);

    // تحلیل پاسخ
    if ($http_code == 301 || $http_code == 302) {
        return array('code' => $http_code, 'new_location' => $final_url);
    } elseif ($final_url !== $encoded_url) {
        // URL تغییر کرده، به احتمال زیاد ریدایرکت 301 یا مشابه وجود داشته
        return array('code' => '301-like', 'new_location' => $final_url);
    } elseif ($http_code == 200) {
        // بررسی محتوای HTML برای پیام‌های خاص
        if (strpos($content, '301 Moved Permanently') !== false) {
            return array('code' => '301-in-html', 'message' => '301 found in HTML content');
        }
        return array('code' => 200);
    } else {
        return array('code' => $http_code);
    }
}



// تابع کمکی برای کدگذاری URL (به ویژه بخش path) به صورت percent-encoded
function encodeUrl($url)
{
    $parts = parse_url($url);
    $scheme   = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host     = isset($parts['host']) ? $parts['host'] : '';
    $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
    $user     = isset($parts['user']) ? $parts['user'] : '';
    $pass     = isset($parts['pass']) ? ':' . $parts['pass']  : '';
    $pass     = ($user || $pass) ? "$pass@" : '';
    
    // بررسی و کدگذاری مسیر فقط اگر قبلاً کدگذاری نشده باشد
    $path = '';
    if (isset($parts['path'])) {
        // حفظ اسلش ابتدایی
        $startsWithSlash = substr($parts['path'], 0, 1) === '/';
        
        // جداسازی بخش‌های مسیر
        $pathParts = explode('/', ltrim($parts['path'], '/'));
        $encodedParts = [];
        
        foreach ($pathParts as $part) {
            // بررسی اینکه آیا بخش قبلاً کدگذاری شده است یا نه
            if (preg_match('/%[0-9A-F]{2}/i', $part)) {
                // اگر کاراکترهای درصد در مسیر وجود دارد، احتمالا قبلاً کدگذاری شده
                $encodedParts[] = $part;
            } else {
                // در غیر این صورت، کدگذاری کن
                $encodedParts[] = rawurlencode($part);
            }
        }
        
        // بازسازی مسیر
        $path = ($startsWithSlash ? '/' : '') . implode('/', $encodedParts);
    }
    
    $query    = isset($parts['query']) ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    
    return "$scheme$user$pass$host$port$path$query$fragment";
}



// تابع جامع برای دریافت محتوای HTML با استفاده از cURL (بدون گزارشات اضافی)
function fetch_html_with_curl($url)
{
    // ابتدا URL را به صورت صحیح کدگذاری می‌کنیم
    $encodedUrl = encodeUrl(trim($url));
    
    // استخراج اطلاعات URL برای تعیین Host و Referer
    $parsedUrl = parse_url($encodedUrl);
    $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
    $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'http';
    $referer = $host ? $scheme . '://' . $host . '/' : '';

    // تعریف چند مجموعه هدر برای تنوع در درخواست
    $headerSets = [
        [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36",
            "Accept: */*",
            "Accept-Encoding: deflate, gzip",
            "Accept-Language: en-US,en;q=0.5",
            "Connection: keep-alive",
            "Referer: $referer",
            "Host: $host"
        ],
        [
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36",
            "Accept: */*",
            "Accept-Encoding: deflate, gzip",
            "Accept-Language: fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7",
            "Connection: keep-alive",
            "Referer: $referer",
            "Host: $host"
        ],
        [
            "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.96 Safari/537.36",
            "Accept: */*",
            "Accept-Encoding: deflate, gzip",
            "Accept-Language: en-GB,en;q=0.9",
            "Connection: keep-alive",
            "Referer: $referer",
            "Host: $host"
        ]
    ];

    // انتخاب تصادفی یکی از مجموعه‌های هدر
    $selectedHeaders = $headerSets[array_rand($headerSets)];
    // error_log("Selected Request Headers: " . print_r($selectedHeaders, true)); // لاگ حذف شده

    // مقداردهی اولیه cURL
    $ch = curl_init();

    // تنظیمات cURL
    curl_setopt($ch, CURLOPT_URL, $encodedUrl);                     // استفاده از URL کدگذاری شده
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                 // برگرداندن خروجی به جای چاپ مستقیم
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);                 // دنبال کردن ریدایرکت‌ها
    curl_setopt($ch, CURLOPT_HTTPHEADER, $selectedHeaders);           // ارسال هدرهای انتخاب شده
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');                // ذخیره کوکی‌ها
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');               // استفاده از کوکی‌ها
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);                          // محدودیت زمانی - افزایش برای سایت‌های کند
    curl_setopt($ch, CURLOPT_HEADER, true);                         // دریافت هدرها همراه با بدنه (جداسازی بعداً)
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);    // استفاده از HTTP/1.1
    curl_setopt($ch, CURLOPT_ENCODING, "");                         // اجازه به cURL برای مدیریت فشرده‌سازی

    // تنظیمات مربوط به SSL (در محیط‌های تستی)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    // اجرای درخواست
    $raw_response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    // بستن ارتباط cURL
    curl_close($ch);

    if ($raw_response === false) {
        return '';
    }

    // جداسازی body از headers - مشکل اصلی: CURLOPT_HEADER=true باعث می‌شد headers با body مخلوط شوند
    $body = substr($raw_response, $header_size);

    return $body;
}






// Encode the URL
// فرض کنید url برابر با http://example.com/سلام باشد.
// الگوی منظم /[^\x20-\x7f]/ کاراکترهای غیر ASCII مانند س, ل, ا, م را پیدا می‌کند.
// تابع callback این کاراکترها را به صورت درصد-رمزگذاری شده تبدیل می‌کند.
// نتیجه نهایی چیزی شبیه به http://example.com/%D8%B3%D9%84%D8%A7%D9%85 خواهد بود.
function encode_persian_chracter_allowed_url($url)
{
    $encoded_url = preg_replace_callback('/[^\x20-\x7f]/', function ($matches) {
        return rawurlencode($matches[0]);
    }, $url);
    return $encoded_url;
}
/**
 * Extract single element by CSS/XPath selector
 */
function dastyar_extract_by_selector($xpath_obj, $selector, $attr = '') {
    $xpath_query = dastyar_css_to_xpath($selector);
    if (empty($xpath_query)) {
        return '';
    }

    $nodes = $xpath_obj->query($xpath_query);
    if ($nodes === false || $nodes->length === 0) {
        return '';
    }

    $node = $nodes->item(0);
    $tag_name = strtolower($node->nodeName);

    if ($tag_name === 'meta' && empty($attr)) {
        return trim($node->getAttribute('content'));
    }

    if (!empty($attr)) {
        return trim($node->getAttribute($attr));
    }
    
    // For body extraction, we might need inner HTML instead of just textContent?
    // Wait, the client plugin uses $html->find($body_selector, 0)->innertext
    // DOMXPath does not have a native innerHTML property.
    return dastyar_get_inner_html($node);
}

function dastyar_get_inner_html($node) {
    $innerHTML = "";
    $children  = $node->childNodes;
    foreach ($children as $child) {
        $innerHTML .= $node->ownerDocument->saveHTML($child);
    }
    return $innerHTML;
}

function dastyar_css_to_xpath($selector) {
    if (empty($selector)) return '';
    if (strpos($selector, '//') === 0) return $selector; // Already XPath

    $selector = trim($selector);
    $selector = preg_replace('/\s*>\s*/', ' > ', $selector);
    $selector = preg_replace('/\s+/', ' ', $selector);

    $parts = explode(' ', $selector);
    $xpath_parts = [];
    $next_is_child = false;

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '>') {
            $next_is_child = true;
            continue;
        }

        $xpath_part = dastyar_parse_single_css_part($part);
        
        if (empty($xpath_parts)) {
            $xpath_parts[] = '//' . $xpath_part;
        } else {
            if ($next_is_child) {
                $xpath_parts[] = '/' . $xpath_part;
                $next_is_child = false;
            } else {
                $xpath_parts[] = '//' . $xpath_part;
            }
        }
    }
    return implode('', $xpath_parts);
}

function dastyar_parse_single_css_part($part) {
    $tag = '*';
    if (preg_match('/^[a-zA-Z0-9\-]+/', $part, $matches)) {
        $tag = $matches[0];
        $part = substr($part, strlen($tag));
    }

    $conditions = [];

    if (preg_match_all('/#([a-zA-Z0-9\-_]+)/', $part, $matches)) {
        foreach ($matches[1] as $id) {
            $conditions[] = "@id='$id'";
        }
    }

    if (preg_match_all('/\.([a-zA-Z0-9\-_]+)/', $part, $matches)) {
        foreach ($matches[1] as $class) {
            $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' $class ')";
        }
    }

    if (preg_match_all('/\[([a-zA-Z\-]+)=["\']?([^"\'\]]+)["\']?\]/', $part, $matches)) {
        foreach ($matches[1] as $i => $attr) {
            $val = $matches[2][$i];
            $conditions[] = "@$attr='$val'";
        }
    }
    
    $index = null;
    if (preg_match('/:nth-of-type\((\d+)\)/', $part, $matches)) {
        $index = $matches[1];
    }

    $result = $tag;
    if (!empty($conditions)) {
        $result .= '[' . implode(' and ', $conditions) . ']';
    }
    
    if ($index !== null) {
        $result .= '[' . $index . ']';
    }

    return $result;
}
