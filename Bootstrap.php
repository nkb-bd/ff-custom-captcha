<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Models\FormMeta;
use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Services\FormBuilder\BaseFieldManager;
use \FluentForm\Framework\Helpers\ArrayHelper;

class FFCustomField extends BaseFieldManager
{
    public function __construct()
    {
        parent::__construct(
            'ff_custom_recaptcha',
            'Recaptcha',
            ['confirm', 'check'],
            'general'
        );
        
        add_filter("fluentform_validate_input_item_{$this->key}", [$this, 'validate'], 10, 5);
        $this->hideFieldFormEntries();
    }
    
    public function getComponent()
    {
        return [
            'index'          => 16,
            'element'        => $this->key,
            'attributes'     => [
                'name'        => $this->key,
                'class'       => '',
                'value'       => '',
                'type'        => 'text',
                'placeholder' => __('Answer', 'custom-captcha-field-for-fluent-forms')
            ],
            'settings'       => [
                'container_class'    => '',
                'placeholder'        => '',
                'label'              => $this->title,
                'label_placement'    => '',
                'help_message'       => '',
                'error_message'      => __('Recaptcha does not match! Please reload and try again',
                    'custom-captcha-field-for-fluent-forms'),
                'captcha_answer'     => '',
                'captcha_type'       => 'image',
                'text_color'         => '255, 255, 255',
                'bg_color'           => '255, 140, 0',
                'conditional_logics' => []
            ],
            'editor_options' => [
                'title'      => $this->title . ' Field',
                'icon_class' => 'el-icon-phone-outline',
                'template'   => 'inputText'
            ],
        ];
    }
    
    public function getGeneralEditorElements()
    {
        return [
            'label',
            'placeholder',
            'label_placement',
        ];
    }
    
    public function generalEditorElement()
    {
        return [
            'captcha_type'   => [
                'template' => 'select',
                'label'    => 'Captcha Type',
                'options'  => [
                    [
                        'value' => 'image',
                        'label' => __('Image', 'custom-captcha-field-for-fluent-forms'),
                    ],
                    [
                        'value' => 'math',
                        'label' => __('Math', 'custom-captcha-field-for-fluent-forms'),
                    ],
                    [
                        'value' => 'text',
                        'label' => __('Text', 'custom-captcha-field-for-fluent-forms'),
                    ],
                ]
            ],
            'captcha_answer' => [
                'template'   => 'inputText',
                'label'      => 'Captcha Answer',
                'dependency' => [
                    'depends_on' => 'settings/captcha_type',
                    'value'      => 'text',
                    'operator'   => '==',
                ],
            ],
            'error_message'  => [
                'template' => 'inputText',
                'label'    => 'Error Message',
            ],
            'text_color'  => [
                'template' => 'inputText',
                'label'    => 'Text Color',
            ],
            'bg_color'  => [
                'template' => 'inputText',
                'label'    => 'Background Color',
            ],
        ];
    }
    
    public function validate($errorMessage, $field, $formData, $fields, $form)
    {
        $name = sanitize_text_field(ArrayHelper::get($field, 'raw.attributes.name'));
        $value = sanitize_text_field(ArrayHelper::get($formData, $name));
        $message = wp_kses_post(ArrayHelper::get($field, 'raw.settings.error_message'));
        $type = sanitize_text_field(ArrayHelper::get($field, 'raw.settings.captcha_type'));
    
        $codes = ffc_manage_captcha_file('read');
        $valid = false;
    
        if ($type == 'image') {
            foreach ($codes as $code) {
                if (strpos($code, 'image,') === 0 && substr($code, 6) === $value) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                $errorMessage = [$message];
            }
        } elseif ($type == 'math') {
            foreach ($codes as $code) {
                if (strpos($code, 'math,') === 0) {
                    $math = substr($code, 5);
                    $sanitized_expression = preg_replace('/[^0-9+\-\/\*%]/', '', $math);
                    if (!empty($sanitized_expression)) {
                        $result = eval("return $sanitized_expression;");
                        if (intval($value) === $result) {
                            $valid = true;
                            break;
                        }
                    }
                }
            }
            if (!$valid) {
                $errorMessage = [$message];
            }
        } else {
            $textCaptchaValue = sanitize_text_field(ArrayHelper::get($field, 'raw.settings.captcha_answer'));
            if ($value !== $textCaptchaValue) {
                $errorMessage = [$message];
            }
        }
        return $errorMessage;
    }
    
    public function render($data, $form)
    {
        $data['attributes']['id'] = $this->makeElementId($data, $form);
        $type = sanitize_text_field(ArrayHelper::get($data, 'settings.captcha_type'));
        $bgColor = sanitize_text_field(ArrayHelper::get($data, 'settings.bg_color'));
        $bgColor = $this->textColorToRgbArray($bgColor);
        $fontColor = sanitize_text_field(ArrayHelper::get($data, 'settings.text_color'));
        $fontColor = $this->textColorToRgbArray($fontColor);
    
        $codes = ffc_manage_captcha_file('read');
        $latest_code = '';
    
        if ($type == 'image') {
            foreach ($codes as $code) {
                if (strpos($code, 'image,') === 0) {
                    $latest_code = substr($code, 6);
                    break;
                }
            }
            $captcha_image = $this->generateImageWithCode($latest_code, $bgColor, $fontColor);
            echo '<img src="data:image/png;base64,' . esc_attr(base64_encode($captcha_image)) . '" alt="Captcha Image" />';
        } elseif ($type == 'math') {
            foreach ($codes as $code) {
                if (strpos($code, 'math,') === 0) {
                    $latest_code = substr($code, 5);
                    break;
                }
            }
            $captcha_image = $this->generateImageWithCode($latest_code, $bgColor, $fontColor);
            echo '<img src="data:image/png;base64,' . esc_attr(base64_encode($captcha_image)) . '" alt="Captcha Image" />';
        }
        return (new FluentForm\App\Services\FormBuilder\Components\Text())->compile($data, $form);
    
    }
    
    private function hideFieldFormEntries()
    {
        add_filter('fluentform/all_entry_labels', function ($formLabels, $form_id) {
            $form = wpFluent()->table('fluentform_forms')->find($form_id);
            $fields = FormFieldsParser::getInputsByElementTypes($form, ['ff_custom_recaptcha']);
            if (is_array($fields) && !empty($fields)) {
                ArrayHelper::forget($formLabels, array_keys($fields));
            }
            return $formLabels;
        }, 10, 2);
        add_filter('fluentform/all_entry_labels_with_payment', function ($formLabels, $test, $form) {
            $confirmField = FormFieldsParser::getInputsByElementTypes($form, ['ff_custom_recaptcha']);
            if (is_array($confirmField) && !empty($confirmField)) {
                ArrayHelper::forget($formLabels, array_keys($confirmField));
            }
            return $formLabels;
        }, 10, 3);
    }
    
    public function textColorToRgbArray($colorText) {
        $components = explode(',', $colorText);
        return array_map('intval', $components);
    }
    
    public function isValidColor($color)
    {
        foreach ($color as $value) {
            if (!is_int($value) || $value < 0 || $value > 255) {
                return false;
            }
        }
        return true;
    }
    public function generateImageWithCode($captcha_code, $background_color = [0xFF, 0x8c, 0x00], $text_color = [0xFF, 0xFF, 0xFF])
    {
        if (!is_array($background_color) || count($background_color) != 3 || !$this->isValidColor($background_color)) {
            $background_color = [0xFF, 0x8c, 0x00];
        }
    
        if (!is_array($text_color) || count($text_color) != 3 || !$this->isValidColor($text_color)) {
            $text_color = [0xFF, 0xFF, 0xFF];
        }
    
        ob_start();
        
        // Create a larger canvas to accommodate the pixelation effect
        $im = imagecreatetruecolor(440, 70);
        
        $background = imagecolorallocate($im, $background_color[0], $background_color[1], $background_color[2]);
        $text = imagecolorallocate($im, $text_color[0], $text_color[1], $text_color[2]);
        
        imagefilledrectangle($im, 0, 0, 440, 70, $background);
        
        $font_file = FF_CUSTOM_CAPTCHA_DIR_PATH.'assets/FreeMono.ttf';
        imagefttext($im, 30, 0, 5, 45, $text, $font_file, $captcha_code);
        
        // Resize the image to create pixelation effect
        $resized_im = imagecreatetruecolor(220, 35);
        imagecopyresized($resized_im, $im, 0, 0, 0, 0, 220, 35, 440, 70);
        
        imagepng($resized_im);
        
        imagedestroy($im);
        imagedestroy($resized_im);
        
        return ob_get_clean();
    }
    
}

function ffC_captcha_code()
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $captchaCode = '';
    
    $captchaCode .= $characters[wp_rand(0, 25)];
    $captchaCode .= $characters[wp_rand(26, 51)];
    $captchaCode .= $characters[wp_rand(52, 61)];
    
    for ($i = 0; $i < 3; $i++) {
        $captchaCode .= $characters[wp_rand(0, 61)];
    }
    return $captchaCode;
}

function ffc_math_problem()
{
    $num1 = wp_rand(0, 10);
    $num2 = wp_rand(0, 10);
    $operator = wp_rand(0, 1) == 1 ? '+' : '-';
    $problem = "$num1 $operator $num2";
    return $problem;
}

function ffc_generate_code($force = false) {
    $captchaCode = ffC_captcha_code();
    $problem = ffc_math_problem();
    
    if ($force || !isset($_SESSION['ff_custom_recaptcha_image_code'])) {
        $_SESSION['ff_custom_recaptcha_image_code'] = $captchaCode;
        ffc_manage_captcha_file('write', "image,$captchaCode");
    }
    
    if ($force || !isset($_SESSION['ff_custom_recaptcha_math_problem'])) {
        $_SESSION['ff_custom_recaptcha_math_problem'] = $problem;
        ffc_manage_captcha_file('write', "math,$problem");
    }
}

function ffc_manage_captcha_file($action, $data = null) {
    $file_path = FF_CUSTOM_CAPTCHA_DIR_PATH . '/captcha_codes.txt';
    
    if ($action === 'read') {
        if (file_exists($file_path)) {
            return file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }
        return [];
    } elseif ($action === 'write') {
        $current_codes = ffc_manage_captcha_file('read');
        array_unshift($current_codes, $data);
        $current_codes = array_slice($current_codes, 0, 100); // Keep only the last 100 entries
        file_put_contents($file_path, implode("\n", $current_codes));
    }
}

add_action('init', function () {
    ffc_generate_code();
});

add_action('fluentform/submission_inserted', function () {
    ffc_generate_code($force = true);
});

