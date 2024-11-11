<?php
class filepond_helper {
    /**
     * Get JavaScript files
     * @return string|void Returns HTML string in frontend, adds scripts via rex_view in backend
     */
    public static function getScripts():string|void {
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
            return;
        }

        $output = '';
        foreach($jsFiles as $file) {
            $output .= '<script type="text/javascript" src="' . $file . '" defer></script>' . PHP_EOL;
        }
        
        return $output;
    }

    /**
     * Get CSS files
     * @return string|void Returns HTML string in frontend, adds styles via rex_view in backend
     */
    public static function getStyles():string|void {
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
            return;
        }

        $output = '';
        foreach($cssFiles as $file) {
            $output .= '<link rel="stylesheet" type="text/css" href="' . $file . '">' . PHP_EOL;
        }
        
        return $output;
    }

    /**
     * Get input field with FilePond configuration
     * 
     * @param string $name Input name
     * @param string $value Current value
     * @param array $options Additional options
     * @return string HTML output
     */
    public static function getInput($name, $value = '', array $options = []):string {
        // Default options
        $defaults = [
            'category' => rex_config::get('filepond_uploader', 'category_id', 0),
            'maxFiles' => rex_config::get('filepond_uploader', 'max_files', 30),
            'allowedTypes' => rex_config::get('filepond_uploader', 'allowed_types', 'image/*'),
            'maxSize' => rex_config::get('filepond_uploader', 'max_filesize', 10),
            'lang' => rex_config::get('filepond_uploader', 'lang', 'en_gb'),
            'required' => false,
            'class' => '',
            'id' => ''
        ];

        // Merge options
        $options = array_merge($defaults, $options);
        
        // Clean value
        $value = str_replace(['"', ' '], '', $value);

        // Generate unique ID if not provided
        if (empty($options['id'])) {
            $options['id'] = 'filepond-' . uniqid();
        }

        return sprintf(
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
            $name,
            $options['id'],
            $value,
            trim('filepond-input ' . $options['class']),
            $options['category'],
            $options['maxFiles'],
            $options['allowedTypes'],
            $options['maxSize'],
            $options['lang'],
            $options['required'] ? 'required' : ''
        );
    }
}
