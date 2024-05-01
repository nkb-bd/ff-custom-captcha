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
            $value = (int)$value;
            $result = eval("return $expression;");
            if ($value !== $result) {
                $errorMessage = [$message];
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
        if ($type == 'image') {
            $captchaCode = $_SESSION['ff_custom_recaptcha_image_code'] ?? false;
            $captcha_image = $this->generateImageWithCode($captchaCode);
            echo '<img src="data:image/png;base64,' . base64_encode($captcha_image) . '" alt="Captcha Image" />';
        } elseif ($type == 'math') {
            $math = $_SESSION['ff_custom_recaptcha_math_problem'] ?? false;
            $captcha_image = $this->generateImageWithCode($math);
            echo '<img src="data:image/png;base64,' . base64_encode($captcha_image) . '" alt="Captcha Image" />';
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
    
    public function generateImageWithCode($captcha_code)
    {
        ob_start();
        
        $im = imagecreatetruecolor(220, 35);
        $orange = imagecolorallocate($im, 0xFF, 0x8c, 0x00);
        $white = imagecolorallocate($im, 0xFF, 0xFF, 0xFF);
        imagefilledrectangle($im, 0, 0, 220, 35, $orange);
        $font_file = FF_CUSTOM_CAPTCHA_DIR_PATH.'assets/FreeMono.ttf';
        imagefttext($im, 30, 0, 5, 30, $white, $font_file, $captcha_code);
        imagepng($im);
        imagedestroy($im);
        
        return ob_get_clean();
    }
    
}

function ffC_captcha_code()
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $captchaCode = '';
    
    $captchaCode .= $characters[rand(0, 25)];
    $captchaCode .= $characters[rand(26, 51)];
    $captchaCode .= $characters[rand(52, 61)];
    
    for ($i = 0; $i < 3; $i++) {
        $captchaCode .= $characters[rand(0, 61)];
    }
    return $captchaCode;
}

function ffc_math_problem()
{
    $num1 = rand(0, 10);
    $num2 = rand(0, 10);
    $operator = rand(0, 1) == 1 ? '+' : '-';
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

