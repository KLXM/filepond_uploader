<?php
declare(strict_types=1);

if (rex::isBackend()) {
    rex_yform::addTemplatePath($this->getPath('ytemplates'));
}

class filepond_helper {
    /**
     * Default configuration options
     */
    private const array DEFAULT_OPTIONS = [
        'required' => false,
        'class' => '',
        'id' => '',
    ];

    /**
     * Get JavaScript files
     * @return string Returns HTML string in frontend, empty string in backend after adding scripts via rex_view
     */
    public static function getScripts(): string {
        $addon = rex_addon::get('filepond_uploader');
        
        $jsFiles = [
            $addon->getAssetsUrl('filepond/plugins/filepond-plugin-file-validate-type.js'),
            $addon->getAssetsUrl('filepond/plugins/filepond-plugin-file-validate-size.js'),
            $addon->getAssetsUrl('filepond/plugins/filepond-plugin-image-preview.js'),
            $addon->getAssetsUrl('filepond/filepond.js'),
            $addon->getAssetsUrl('filepond_widget.js')
        ];

        if (rex::isBackend()) {
            foreach($jsFiles as $file) {
                rex_view::addJsFile($file);
            }
            return '';
        }

        return implode(PHP_EOL, array_map(
            fn(string $file): string => sprintf(
                '<script type="text/javascript" src="%s" defer></script>',
                $file
            ),
            $jsFiles
        ));
    }

    /**
     * Get CSS files
     * @return string Returns HTML string in frontend, empty string in backend after adding styles via rex_view
     */
    public static function getStyles(): string {
        $addon = rex_addon::get('filepond_uploader');
        
        $cssFiles = [
            $addon->getAssetsUrl('filepond/filepond.css'),
            $addon->getAssetsUrl('filepond/plugins/filepond-plugin-image-preview.css'),
            $addon->getAssetsUrl('filepond_widget.css')
        ];

        if (rex::isBackend()) {
            foreach($cssFiles as $file) {
                rex_view::addCssFile($file);
            }
            return '';
        }

        return implode(PHP_EOL, array_map(
            fn(string $file): string => sprintf(
                '<link rel="stylesheet" type="text/css" href="%s">',
                $file
            ),
            $cssFiles
        ));
    }

    /**
     * Get default options merged with config values
     */
    private static function getDefaultOptions(): array {
        return [
            'category' => rex_config::get('filepond_uploader', 'category_id', 0),
            'maxFiles' => rex_config::get('filepond_uploader', 'max_files', 30),
            'allowedTypes' => rex_config::get('filepond_uploader', 'allowed_types', 'image/*'),
            'maxSize' => rex_config::get('filepond_uploader', 'max_filesize', 10),
            'lang' => rex_config::get('filepond_uploader', 'lang', 'en_gb'),
            ...self::DEFAULT_OPTIONS
        ];
    }

    /**
     * Clean input value
     */
    private static function cleanValue(string $value): string {
        return str_replace(['"', ' '], '', $value);
    }

    /**
     * Get input field with FilePond configuration
     * 
     * @param string $name Input name
     * @param string $value Current value
     * @param array<string, mixed> $options Additional options
     * @return string HTML output
     */
    public static function getInput(
        string $name,
        string $value = '',
        array $options = []
    ): string {
        // Merge options with defaults
        $options = array_merge(self::getDefaultOptions(), $options);
        
        // Clean value
        $value = self::cleanValue($value);

        // Generate unique ID if not provided
        $options['id'] = $options['id'] ?: 'filepond-' . uniqid();

        return trim(sprintf(
            '<input type="hidden" 
                name="%s" 
                id="%s"
                value="%s"
                class="%s"
                data-widget="filepond"
                data-filepond-cat="%d"
                data-filepond-maxfiles="%d"
                data-filepond-types="%s"
                data-filepond-maxsize="%d"
                data-filepond-lang="%s"
                %s
            >',
            htmlspecialchars($name),
            htmlspecialchars($options['id']),
            htmlspecialchars($value),
            trim('filepond-input ' . htmlspecialchars($options['class'])),
            (int)$options['category'],
            (int)$options['maxFiles'],
            htmlspecialchars($options['allowedTypes']),
            (int)$options['maxSize'],
            htmlspecialchars($options['lang']),
            $options['required'] ? 'required' : ''
        ));
    }
}
