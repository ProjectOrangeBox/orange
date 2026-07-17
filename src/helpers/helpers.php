<?php

declare(strict_types=1);

if (!function_exists('is_closure')) {
    /**
     * Checks if the given variable is a Closure.
     *
     * @param mixed $c The variable to check.
     * @return bool True if $c is a Closure, false otherwise.
     */
    function is_closure($c)
    {
        return $c instanceof \Closure;
    }
}

if (!function_exists('file_put_contents_atomic')) {
    /**
     * Writes content to a file atomically to prevent partial reads by other threads.
     *
     * @param string $filePath The path to the file.
     * @param string $content The content to write.
     * @param int $flags Optional flags for file_put_contents.
     * @param mixed $context Optional stream context.
     * @return int|false The number of bytes written or false on failure.
     */
    function file_put_contents_atomic(string $filePath, string $content, int $flags = 0, $context = null): int|false
    {
        // !multiple exits

        $tempFilePath = $filePath . \hrtime(true);
        $strlen = strlen($content);

        if (file_put_contents($tempFilePath, $content, $flags, $context) !== $strlen) {
            return false;
        }

        // atomic function
        if (rename($tempFilePath, $filePath, $context) === false) {
            return false;
        }

        // flush from the cache if this is a .php file
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($filePath, true);
        } elseif (function_exists('apc_delete_file')) {
            apc_delete_file($filePath);
        }

        return $strlen;
    }
}

if (!function_exists('element')) {
    /**
     * Builds a standard HTML element with attributes and content.
     *
     * @param string $tag The HTML tag name.
     * @param array $attr An array of attributes.
     * @param string $content The inner content of the element.
     * @param bool $escape Whether to escape the content.
     * @return string The generated HTML element.
     */
    function element(string $tag, array $attr = [], string $content = '', bool $escape = true)
    {
        $selfClosing = ['area', 'base', 'br', 'embed', 'hr', 'iframe', 'img', 'input', 'link', 'meta', 'param', 'source'];

        $html = '<' . $tag . ' ' . str_replace("=", '="', http_build_query($attr, '', '" ', PHP_QUERY_RFC3986)) . '">';

        if (!empty($content)) {
            $html .= ($escape) ? htmlentities($content) : $content;
        }

        if (!in_array($tag, $selfClosing)) {
            $html .= '</' . $tag . '>';
        }

        return $html;
    }
}


if (!function_exists('dataUri')) {
    /**
     * Generates a data URI for a file, suitable for embedding in HTML like <img src="...">.
     *
     * @param string $file The path to the file.
     * @return void Outputs the data URI directly.
     */
    function dataUri(string $file)
    {
        echo 'data:' . mime_content_type($file) . ';base64,' . base64_encode(file_get_contents($file));
    }
}

if (!function_exists('convertLabel')) {
    /**
     * Converts a string to a specific case format (e.g., camel, snake, slug).
     *
     * @param string $value The string to convert.
     * @param string $case The target case format.
     * @return string The converted string.
     */
    function convertLabel(string $value, string $case = 'camel'): string
    {
        switch ($case) {
            case 'normalize':
                $value = mb_convert_case($value, MB_CASE_LOWER, mb_detect_encoding($value));
                $value = preg_replace('/[^a-z0-9]/i', '', $value);
                break;
            case 'lower':
                $value = substr($value, 0, 1) . implode(' ', preg_split('/(?=[A-Z])/', substr($value, 1)));
                $value = mb_convert_case($value, MB_CASE_LOWER, mb_detect_encoding($value));
                $value = str_replace('_', ' ', $value);
                break;
            case 'upper':
                $value = substr($value, 0, 1) . implode(' ', preg_split('/(?=[A-Z])/', substr($value, 1)));
                $value = mb_convert_case($value, MB_CASE_UPPER, mb_detect_encoding($value));
                $value = str_replace('_', ' ', $value);
                break;
            case 'title':
                $value = substr($value, 0, 1) . implode(' ', preg_split('/(?=[A-Z])/', substr($value, 1)));
                $value = mb_convert_case($value, MB_CASE_TITLE, mb_detect_encoding($value));
                $value = str_replace('_', ' ', $value);
                break;
            case 'ucfirst':
                $value = substr($value, 0, 1) . implode(' ', preg_split('/(?=[A-Z])/', substr($value, 1)));
                $value = mb_convert_case($value, MB_CASE_LOWER, mb_detect_encoding($value));
                $value = ucfirst(str_replace('_', ' ', $value));
                break;
            case 'camel':
            case 'pascal':
                $value = preg_replace('/([a-z])([A-Z])/', '\\1 \\2', $value);
                $value = preg_replace('@[^a-zA-Z0-9\-_ ]+@', '', $value);
                $value = str_replace(['-', '_'], ' ', $value);
                $value = str_replace(' ', '', ucwords(convertLabel($value, 'lower')));
                $value = substr(convertLabel($value, 'lower'), 0, 1) . substr($value, 1);
                $value = ($case === 'camel') ? lcfirst($value) : ucfirst($value);
                break;
            case 'snake':
                $value = preg_replace('@[^a-zA-Z0-9\-_ ]+@', '', $value);
                $value = mb_convert_case($value, MB_CASE_LOWER, mb_detect_encoding($value));
                $value = str_replace([' ', '-'], '_', $value);
                break;
            case 'slug':
                $value = preg_replace('/[^a-zA-Z0-9 -]/', '', $value);
                $value = mb_strtolower(str_replace(' ', '-', trim($value)));
                $value = preg_replace('/-+/', '-', $value);
                break;
            default:
                throw new InvalidArgumentException('Invalid case: ' . $case);
        }

        return $value;
    }
}

if (!function_exists('esc')) {
    /**
     * Escapes double quotes in a string by replacing them with backslash-double quote.
     *
     * @param string $string The string to escape.
     * @return string The escaped string.
     */
    function esc(string $string): string
    {
        return str_replace('"', '\"', $string);
    }
}

/**
 * Escape html special characters
 */
if (!function_exists('e')) {
    /**
     * Escapes HTML special characters in a string or array recursively.
     *
     * @param mixed $input The input to escape (string or array).
     * @param int $flags Flags for htmlspecialchars.
     * @param string|null $encoding Character encoding.
     * @param bool $doubleEncode Whether to double encode.
     * @return string|array The escaped input.
     */
    function e(mixed $input, int $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, ?string $encoding = null, bool $doubleEncode = true): string|array
    {
        if (!empty($input)) {
            if (is_array($input)) {
                foreach (array_keys($input) as $key) {
                    $input[$key] = e($input[$key], $flags, $encoding, $doubleEncode);
                }
            } else {
                $input = htmlspecialchars($input, $flags, $encoding, $doubleEncode);
            }
        }

        return $input;
    }
}

if (!function_exists('concat')) {
    /**
     * Add the "missing" string concat function
     *
     * Concatenates all provided arguments into a single string.
     *
     * @return string The concatenated string.
     */
    function concat(): string
    {
        return implode('', func_get_args());
    }
}

if (!function_exists('strContains')) {
    /**
     * Polyfill of str_contains()
     */
    function strContains(string $haystack, string $needle): bool
    {
        return empty($needle) || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('nthfield')) {
    /**
     * Retrieves the nth field from a string split by a separator.
     *
     * @param string $string The string to split.
     * @param string $separator The separator to use.
     * @param int $nth The 1-based index of the field to retrieve.
     * @return mixed The nth field or null if not found.
     */
    function nthfield(string $string, string $separator, int $nth): mixed
    {
        $array = explode($separator, $string);

        return $array[--$nth] ?? null;
    }
}

if (!function_exists('after')) {
    /**
     * Returns the substring after the first occurrence of a tag in the string.
     *
     * @param string $tag The tag to search for.
     * @param string $string The string to search in.
     * @return string The substring after the tag.
     */
    function after(string $tag, string $string): string
    {
        return substr($string, strpos($string, $tag) + strlen($tag));
    }
}

if (!function_exists('before')) {
    /**
     * Returns the substring before the first occurrence of a tag in the string.
     *
     * @param string $tag The tag to search for.
     * @param string $string The string to search in.
     * @return string The substring before the tag.
     */
    function before(string $tag, string $string): string
    {
        return substr($string, 0, strpos($string, $tag));
    }
}

if (!function_exists('between')) {
    /**
     * Returns the substring between two tags in the string.
     *
     * @param string $startTag The starting tag.
     * @param string $endTag The ending tag.
     * @param string $string The string to search in.
     * @return string The substring between the tags.
     */
    function between(string $startTag, string $endTag, string $string): string
    {
        return before($endTag, after($startTag, $string));
    }
}

if (!function_exists('left')) {
    /**
     * Returns the leftmost characters of a string.
     *
     * @param string $string The string to extract from.
     * @param int $num The number of characters to return.
     * @return string The leftmost characters.
     */
    function left(string $string, int $num): string
    {
        return substr($string, 0, $num);
    }
}

if (!function_exists('right')) {
    /**
     * Returns the rightmost characters of a string.
     *
     * @param string $string The string to extract from.
     * @param int $num The number of characters to return.
     * @return string The rightmost characters.
     */
    function right(string $string, int $num): string
    {
        return substr($string, -$num);
    }
}

if (!function_exists('mid')) {
    /**
     * Returns a substring starting from a specified position with a given length.
     *
     * @param string $string The string to extract from.
     * @param int $start The 1-based starting position.
     * @param int $length The length of the substring.
     * @return string The extracted substring.
     */
    function mid(string $string, int $start, int $length): string
    {
        return substr($string, $start - 1, $length);
    }
}

if (!function_exists('isAssociative')) {
    /**
     * Checks if an array is associative (i.e., has non-numeric keys).
     *
     * @param array $array The array to check.
     * @return bool True if associative, false otherwise.
     */
    function isAssociative(array $array): bool
    {
        // an empty array is not associative; guard against range(0, -1) returning [0, -1]
        return $array !== [] && array_keys($array) !== range(0, count($array) - 1);
    }
}

if (!function_exists('forceDownload')) {
    /**
     * Forces the download of a file or data by setting appropriate headers and outputting the content.
     *
     * @param string $filename The name of the file to download.
     * @param string $dataOrPath The data to download or the path to the file.
     * @param string|null $contentType The MIME type of the content.
     * @return never This function exits after sending the download.
     */
    function forceDownload(string $filename = '', string $dataOrPath = '', ?string $contentType = null): never
    {
        /**
         * normally these are standalone but this requires the output service
         */
        $outputService = container()->get('output');

        $outputService->flushAll();

        // set the mime based on the file extension if it's not found then use the fall back of bin
        if ($contentType == null) {
            // true to auto detect
            $outputService->contentType(pathinfo($filename, PATHINFO_EXTENSION), 'bin');
        } else {
            $outputService->contentType($contentType, 'bin');
        }

        $outputService->header('Content-Disposition: attachment; filename="' . $filename . '"');
        $outputService->header('Content-Transfer-Encoding: binary');
        $outputService->header('Expires: 0');
        $outputService->header('Pragma: no-cache');

        // if this isn't file and actual file data then we can put it in the output
        if (file_exists($dataOrPath)) {
            $outputService->header('Content-Length: ' . filesize($dataOrPath));
        } else {
            $outputService->header('Content-Length: ' . strlen($dataOrPath));
            $outputService->write($dataOrPath, false);
        }

        // send the headers but don't exit (default)
        $outputService->send();

        // if this an actual file then we will just stream it after we send the header
        if (file_exists($dataOrPath)) {
            readfile($dataOrPath);
        }

        exit(0);
    }
}
