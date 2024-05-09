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
                'placeholder' => __('Answer', 'ff_custom_recaptcha')
            ],
            'settings'       => [
                'container_class'    => '',
                'placeholder'        => '',
                'label'              => $this->title,
                'label_placement'    => '',
                'help_message'       => '',
                'error_message'      => __('Recaptcha does not match! Please reload and try again',
                    'ff_custom_recaptcha'),
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
                        'label' => __('Image', 'fluentform'),
                    ],
                    [
                        'value' => 'math',
                        'label' => __('Math', 'fluentform'),
                    ],
                    [
                        'value' => 'text',
                        'label' => __('Text', 'fluentform'),
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
        $name = ArrayHelper::get($field, 'raw.attributes.name');
        $value = ArrayHelper::get($formData, $name);
        $message = ArrayHelper::get($field, 'raw.settings.error_message');
        $type = ArrayHelper::get($field, 'raw.settings.captcha_type');
        
        if ($type == 'image') {
            $captchaCode = $_SESSION['ff_custom_recaptcha_image_code'] ?? false;
            if ($value !== $captchaCode) {
                $errorMessage = [$message];
            }
        } elseif ($type == 'math') {
            $math = $_SESSION['ff_custom_recaptcha_math_problem'] ?? false;
            $expression = $math;
            $sanitized_expression = preg_replace('/[^0-9+\-\/\*%]/', '', $expression);
            if (!empty($sanitized_expression)) {
                $value = (int)$value;
    
                $result = eval("return $sanitized_expression;");
                if ($value !== $result) {
                    $errorMessage = [$message];
                }
            }
          
        } else {
            $textCaptchaValue = ArrayHelper::get($field, 'raw.settings.captcha_answer');
            if ($value !== $textCaptchaValue) {
                $errorMessage = [$message];
            }
        }
        return $errorMessage;
    }
    
    public function render($data, $form)
    {
        $data['attributes']['id'] = $this->makeElementId($data, $form);
        $type = ArrayHelper::get($data, 'settings.captcha_type');
        $bgColor = ArrayHelper::get($data, 'settings.bg_color');
        $bgColor = $this->textColorToRgbArray($bgColor);
        $fontColor = ArrayHelper::get($data, 'settings.text_color');
        $fontColor = $this->textColorToRgbArray($fontColor);
        if ($type == 'image') {
            $captchaCode = isset($_SESSION['ff_custom_recaptcha_image_code']) ? $_SESSION['ff_custom_recaptcha_image_code'] : false;
        
            $captcha_image = $this->generateImageWithCode($captchaCode, $bgColor, $fontColor);
            echo '<img src="data:image/png;base64,' . esc_attr(base64_encode($captcha_image)) . '" alt="Captcha Image" />';
        } elseif ($type == 'math') {
            $math = isset($_SESSION['ff_custom_recaptcha_math_problem']) ? $_SESSION['ff_custom_recaptcha_math_problem'] : false;
            $captcha_image = $this->generateImageWithCode($math, $bgColor, $fontColor);
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

function ffc_generate_code($force = false)
{
    if (!session_id()) {
        session_start();
    }
    $captchaCode = ffC_captcha_code();
    if (!isset($_SESSION['ff_custom_recaptcha_image_code'])) {
        $_SESSION['ff_custom_recaptcha_image_code'] = $captchaCode;
    }
    
    
    $problem = ffc_math_problem();
    if (!isset($_SESSION['ff_custom_recaptcha_math_problem'])) {
        $_SESSION['ff_custom_recaptcha_math_problem'] = $problem;
    }
    if ($force) {
        $_SESSION['ff_custom_recaptcha_image_code'] = $captchaCode;
        $_SESSION['ff_custom_recaptcha_math_problem'] = $problem;
    }
}

add_action('init', function () {
    ffc_generate_code();
});

add_action('fluentform/submission_inserted', function () {
    ffc_generate_code($force = true);
});

