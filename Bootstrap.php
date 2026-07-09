<?php

if (!defined('ABSPATH')) {
    exit;
}

use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Services\FormBuilder\BaseFieldManager;
use FluentForm\Framework\Helpers\ArrayHelper;

class FFC_CaptchaField extends BaseFieldManager
{
    const TRANSIENT_PREFIX = 'ffc_captcha_';
    const TRANSIENT_TTL = 15 * MINUTE_IN_SECONDS;

    private $validationResults = [];

    public function __construct()
    {
        // element key kept from v1 so fields in already-saved forms keep working
        parent::__construct(
            'ff_custom_recaptcha',
            'Custom Captcha',
            ['captcha', 'confirm', 'check'],
            'general'
        );

        // FF 5+ fires the legacy hook via apply_filters_deprecated alongside the new
        // one, so registering both would consume the one-time token twice
        $hookPrefix = defined('FLUENTFORM_VERSION') && version_compare(FLUENTFORM_VERSION, '5.0', '>=')
            ? 'fluentform/validate_input_item_'
            : 'fluentform_validate_input_item_';

        add_filter($hookPrefix . $this->key, [$this, 'validate'], 10, 5);
        add_filter($hookPrefix . 'input_email', [$this, 'validateEmail'], 10, 5);

        add_action('wp_ajax_ffc_refresh_captcha', [$this, 'refreshCaptcha']);
        add_action('wp_ajax_nopriv_ffc_refresh_captcha', [$this, 'refreshCaptcha']);

        // FF strips inputs that are not registered fields or whitelisted from formData
        add_filter('fluentform/white_listed_fields', [$this, 'whitelistHiddenInputs']);
        add_filter('fluentform_white_listed_fields', [$this, 'whitelistHiddenInputs']);

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
                'error_message'      => __('Captcha does not match! Please try again', 'custom-captcha-field-for-fluent-forms'),
                'captcha_answer'     => '',
                'captcha_type'       => 'image',
                'text_color'         => '68, 68, 68',
                'bg_color'           => 'transparent',
                'block_disposable_emails' => true,
                'enable_time_trap'   => false,
                'min_submit_time'    => 3,
                'conditional_logics' => []
            ],
            'editor_options' => [
                'title'      => $this->title . ' Field',
                'icon_class' => 'el-icon-lock',
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
            'text_color'     => [
                'template' => 'inputText',
                'label'    => 'Text Color (R, G, B)',
            ],
            'bg_color'       => [
                'template' => 'inputText',
                'label'    => 'Background Color (R, G, B or "transparent")',
            ],
            'block_disposable_emails' => [
                'template'  => 'inputYesNoCheckBox',
                'label'     => 'Block Disposable Emails',
                'help_text' => 'Reject submissions where an email field uses a temporary/disposable email domain',
            ],
            'enable_time_trap' => [
                'template'  => 'inputYesNoCheckBox',
                'label'     => 'Enable Time Trap',
                'help_text' => 'Reject submissions completed faster than a human could fill the form',
            ],
            'min_submit_time' => [
                'template'   => 'inputNumber',
                'label'      => 'Minimum Submit Time (seconds)',
                'help_text'  => 'Submissions completed faster than this are treated as bots',
                'dependency' => [
                    'depends_on' => 'settings/enable_time_trap',
                    'value'      => true,
                    'operator'   => '==',
                ],
            ],
        ];
    }

    public function validate($errorMessage, $field, $formData, $fields, $form)
    {
        $name = sanitize_text_field(ArrayHelper::get($field, 'raw.attributes.name'));
        $value = trim(sanitize_text_field(ArrayHelper::get($formData, $name)));
        $message = wp_kses_post(ArrayHelper::get($field, 'raw.settings.error_message'));
        $type = sanitize_text_field(ArrayHelper::get($field, 'raw.settings.captcha_type'));

        if (!$message) {
            $message = __('Captcha does not match! Please try again', 'custom-captcha-field-for-fluent-forms');
        }

        if (ArrayHelper::get($field, 'raw.settings.enable_time_trap') && $this->isSpamSubmission($formData, $field)) {
            return [$message];
        }

        if ($type === 'text') {
            $expected = trim(sanitize_text_field(ArrayHelper::get($field, 'raw.settings.captcha_answer')));
            if ($expected === '' || !hash_equals(strtolower($expected), strtolower($value))) {
                return [$message];
            }
            return $errorMessage;
        }

        $token = sanitize_text_field(ArrayHelper::get($formData, 'ffc_captcha_token'));

        if (!preg_match('/^[a-zA-Z0-9]{20}$/', $token)) {
            return [$message];
        }

        if (isset($this->validationResults[$token])) {
            return $this->validationResults[$token] ? $errorMessage : [$message];
        }

        if ($type === 'math') {
            $value = preg_match('/^\d+$/', $value) ? (string) (int) $value : '';
        }

        $expected = get_transient(self::TRANSIENT_PREFIX . $token);
        $valid = is_string($expected)
            && $value !== ''
            && hash_equals(strtolower($expected), strtolower($value));

        if ($valid) {
            delete_transient(self::TRANSIENT_PREFIX . $token);
        }

        $this->validationResults[$token] = $valid;

        return $valid ? $errorMessage : [$message];
    }

    public function render($data, $form)
    {
        $data['attributes']['id'] = $this->makeElementId($data, $form);
        $type = sanitize_text_field(ArrayHelper::get($data, 'settings.captcha_type'));
        $bgColor = sanitize_text_field(ArrayHelper::get($data, 'settings.bg_color'));
        $textColor = sanitize_text_field(ArrayHelper::get($data, 'settings.text_color'));

        echo $this->challengeMarkup($type, $bgColor, $textColor);
        $this->enqueueAssets();

        return (new FluentForm\App\Services\FormBuilder\Components\Text())->compile($data, $form);
    }

    public function refreshCaptcha()
    {
        $type = sanitize_text_field(ArrayHelper::get($_REQUEST, 'type'));

        if (!in_array($type, ['image', 'math', 'text'], true)) {
            $type = 'image';
        }

        $payload = [
            'ts'    => $this->signedTimestamp(),
            'token' => '',
            'image' => '',
            'text'  => '',
        ];

        if ($type !== 'text') {
            $bgColor = sanitize_text_field(ArrayHelper::get($_REQUEST, 'bg'));
            $textColor = sanitize_text_field(ArrayHelper::get($_REQUEST, 'color'));

            $challenge = $this->createChallenge($type);
            $image = $this->challengeImage($challenge['display'], $bgColor, $textColor);

            $payload['token'] = $challenge['token'];
            $payload['image'] = $image ? 'data:image/png;base64,' . base64_encode($image) : '';
            $payload['text'] = $image ? '' : $challenge['display'];
        }

        wp_send_json_success($payload);
    }

    private function createChallenge($type)
    {
        if ($type === 'math') {
            $num1 = wp_rand(0, 10);
            $num2 = wp_rand(0, 10);

            if (wp_rand(0, 1)) {
                $display = "$num1 + $num2";
                $answer = $num1 + $num2;
            } else {
                $bigger = max($num1, $num2);
                $smaller = min($num1, $num2);
                $display = "$bigger - $smaller";
                $answer = $bigger - $smaller;
            }
        } else {
            $display = $this->randomCode();
            $answer = $display;
        }

        $token = wp_generate_password(20, false);
        set_transient(self::TRANSIENT_PREFIX . $token, (string) $answer, self::TRANSIENT_TTL);

        return ['token' => $token, 'display' => $display];
    }

    private function randomCode()
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $code = $characters[wp_rand(0, 25)];
        $code .= $characters[wp_rand(26, 51)];
        $code .= $characters[wp_rand(52, 61)];

        for ($i = 0; $i < 3; $i++) {
            $code .= $characters[wp_rand(0, 61)];
        }

        return $code;
    }

    private function challengeMarkup($type, $bgColor, $textColor)
    {
        $html = '<div class="ffc-captcha" data-type="' . esc_attr($type) . '" data-bg="' . esc_attr($bgColor) . '" data-color="' . esc_attr($textColor) . '">';

        if ($type === 'image' || $type === 'math') {
            $challenge = $this->createChallenge($type);
            $image = $this->challengeImage($challenge['display'], $bgColor, $textColor);

            if ($image) {
                $html .= '<img class="ffc-captcha-image" src="data:image/png;base64,' . esc_attr(base64_encode($image)) . '" alt="' . esc_attr__('CAPTCHA challenge', 'custom-captcha-field-for-fluent-forms') . '" style="vertical-align:middle;" />';
            } else {
                $html .= '<span class="ffc-captcha-text" style="font-family:monospace;font-size:20px;letter-spacing:4px;vertical-align:middle;">' . esc_html($challenge['display']) . '</span>';
            }

            $html .= '<button type="button" class="ffc-captcha-refresh" aria-label="' . esc_attr__('Get a new captcha challenge', 'custom-captcha-field-for-fluent-forms') . '" title="' . esc_attr__('Refresh captcha', 'custom-captcha-field-for-fluent-forms') . '" style="background:none;border:none;cursor:pointer;font-size:20px;line-height:1;vertical-align:middle;padding:0 8px;">&#8635;</button>';
            $html .= '<input type="hidden" class="ffc-captcha-token" name="ffc_captcha_token" value="' . esc_attr($challenge['token']) . '" />';
        }

        $html .= '<input type="hidden" class="ffc-captcha-ts" name="ffc_captcha_ts" value="' . esc_attr($this->signedTimestamp()) . '" />';
        $html .= '</div>';

        return $html;
    }

    public function whitelistHiddenInputs($fields)
    {
        $fields[] = 'ffc_captcha_token';
        $fields[] = 'ffc_captcha_ts';
        return $fields;
    }

    public function validateEmail($errorMessage, $field, $formData, $fields, $form)
    {
        if ($errorMessage) {
            return $errorMessage;
        }

        if (!$this->emailProtectionEnabled($fields)) {
            return $errorMessage;
        }

        $name = sanitize_text_field(ArrayHelper::get($field, 'raw.attributes.name'));
        $value = strtolower(trim(sanitize_text_field(ArrayHelper::get($formData, $name))));

        if (!$value || strpos($value, '@') === false) {
            return $errorMessage;
        }

        $domain = substr(strrchr($value, '@'), 1);

        foreach ($this->blockedEmailDomains() as $blocked) {
            if ($domain === $blocked || substr($domain, -strlen('.' . $blocked)) === '.' . $blocked) {
                return [__('Temporary or disposable email addresses are not accepted', 'custom-captcha-field-for-fluent-forms')];
            }
        }

        return $errorMessage;
    }

    private function emailProtectionEnabled($fields)
    {
        foreach ($fields as $field) {
            if (ArrayHelper::get($field, 'element') === $this->key) {
                return (bool) ArrayHelper::get($field, 'raw.settings.block_disposable_emails');
            }
        }

        return false;
    }

    private function blockedEmailDomains()
    {
        return apply_filters('ffc_captcha_blocked_email_domains', [
            '10minutemail.com',
            '10minutemail.net',
            '20minutemail.com',
            '33mail.com',
            'anonbox.net',
            'burnermail.io',
            'crazymailing.com',
            'cs.email',
            'discard.email',
            'discardmail.com',
            'disposablemail.com',
            'dispostable.com',
            'dropmail.me',
            'emailfake.com',
            'emailondeck.com',
            'fakeinbox.com',
            'getnada.com',
            'grr.la',
            'guerrillamail.com',
            'guerrillamail.info',
            'guerrillamailblock.com',
            'inboxkitten.com',
            'mail-temp.com',
            'mail.tm',
            'mailcatch.com',
            'maildrop.cc',
            'mailexpire.com',
            'mailinator.com',
            'mailnesia.com',
            'mailpoof.com',
            'mailsac.com',
            'mintemail.com',
            'minuteinbox.com',
            'moakt.com',
            'mohmal.com',
            'mytrashmail.com',
            'nada.email',
            'pokemail.net',
            'sharklasers.com',
            'spam.la',
            'spam4.me',
            'spambox.us',
            'spamgourmet.com',
            'temp-mail.io',
            'temp-mail.org',
            'tempail.com',
            'tempinbox.com',
            'tempmail.com',
            'tempmailaddress.com',
            'tempmailo.com',
            'tempr.email',
            'throwawaymail.com',
            'tmpmail.net',
            'tmpmail.org',
            'trashmail.com',
            'trashmail.de',
            'yopmail.com',
            'yopmail.fr',
            'yopmail.net',
        ]);
    }

    private function isSpamSubmission($formData, $field)
    {
        $stamp = sanitize_text_field(ArrayHelper::get($formData, 'ffc_captcha_ts'));
        $parts = explode('|', $stamp);

        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return true;
        }

        if (!hash_equals($this->signTimestamp($parts[0]), $parts[1])) {
            return true;
        }

        $age = time() - (int) $parts[0];
        $minSeconds = max(1, (int) ArrayHelper::get($field, 'raw.settings.min_submit_time', 3));
        $minSeconds = (int) apply_filters('ffc_captcha_min_submit_seconds', $minSeconds);

        return $age < $minSeconds || $age > DAY_IN_SECONDS;
    }

    private function signedTimestamp()
    {
        $timestamp = (string) time();
        return $timestamp . '|' . $this->signTimestamp($timestamp);
    }

    private function signTimestamp($timestamp)
    {
        return hash_hmac('sha256', (string) $timestamp, wp_salt('nonce'));
    }

    private function challengeImage($text, $bgColor, $textColor)
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagefttext')) {
            return '';
        }

        $transparent = $bgColor === '' || strtolower($bgColor) === 'transparent';
        $bg = $this->toRgb($bgColor, [255, 140, 0]);
        $fg = $this->toRgb($textColor, [68, 68, 68]);

        $im = imagecreatetruecolor(440, 70);
        imagealphablending($im, false);
        imagesavealpha($im, true);

        $background = $transparent
            ? imagecolorallocatealpha($im, 0, 0, 0, 127)
            : imagecolorallocatealpha($im, $bg[0], $bg[1], $bg[2], 0);

        imagefill($im, 0, 0, $background);
        imagealphablending($im, true);

        $color = imagecolorallocate($im, $fg[0], $fg[1], $fg[2]);
        imagefttext($im, 30, 0, 5, 45, $color, FF_CUSTOM_CAPTCHA_DIR_PATH . 'assets/FreeMono.ttf', $text);

        $resized = imagecreatetruecolor(220, 35);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresized($resized, $im, 0, 0, 0, 0, 220, 35, 440, 70);

        ob_start();
        imagepng($resized);
        imagedestroy($im);
        imagedestroy($resized);

        return ob_get_clean();
    }

    private function toRgb($colorText, $fallback)
    {
        $components = array_map('intval', explode(',', $colorText));

        if (count($components) !== 3) {
            return $fallback;
        }

        foreach ($components as $component) {
            if ($component < 0 || $component > 255) {
                return $fallback;
            }
        }

        return $components;
    }

    private function enqueueAssets()
    {
        wp_enqueue_script(
            'ffc-captcha',
            FF_CUSTOM_CAPTCHA_URL . 'assets/ffc-captcha.js',
            ['jquery'],
            FF_CUSTOM_CAPTCHA_VERSION,
            true
        );

        wp_localize_script('ffc-captcha', 'ffcCaptcha', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);
    }

    private function hideFieldFormEntries()
    {
        add_filter('fluentform/all_entry_labels', function ($formLabels, $formId) {
            $form = wpFluent()->table('fluentform_forms')->find($formId);
            $fields = FormFieldsParser::getInputsByElementTypes($form, [$this->key]);
            if (is_array($fields) && !empty($fields)) {
                ArrayHelper::forget($formLabels, array_keys($fields));
            }
            return $formLabels;
        }, 10, 2);

        add_filter('fluentform/all_entry_labels_with_payment', function ($formLabels, $test, $form) {
            $fields = FormFieldsParser::getInputsByElementTypes($form, [$this->key]);
            if (is_array($fields) && !empty($fields)) {
                ArrayHelper::forget($formLabels, array_keys($fields));
            }
            return $formLabels;
        }, 10, 3);
    }
}
