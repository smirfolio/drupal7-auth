<?php


namespace Drupal\obiba_agate\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\obiba_agate\ObibaAgate;
use Drupal\user\AccountForm;
use Drupal\Core\Url;
use Drupal\Core\Link;


class AgateRegisterForm extends  AccountForm {
    protected $hasRealm;
    public function __construct(EntityRepositoryInterface $entity_repository,
                                LanguageManagerInterface $language_manager,
                                EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
                                TimeInterface $time = NULL) {
        parent::__construct($entity_repository, $language_manager, $entity_type_bundle_info, $time);
        $this->hasRealm = $this->hasRealmOptions();
    }

    /**
     * {@inheritdoc}.
     */
    public function getFormId() {
        return ObibaAgate::AGATE_USER_REGISTER_FORM;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildForm($form, $form_state);
        if(!\Drupal::currentUser()->hasPermission('administrator')){
            $config =  \Drupal::config(ObibaAgate::AGATE_SERVER_SETTINGS);
            $form['#prefix'] = '<div class="row">
                <div class="col-md-2"></div>
                <div class="well col-md-8" id="update-field">';
            $form['#suffix'] = '</div>
                <div class="col-md-2"></div>
                </div>';
            $form['account']['pass']['#type'] = 'hidden';
            $form['account']['pass']['#required'] = false;

            if($this->hasRealm){
                $form['test_realm'] =  [
                    '#weight' => 24,
                    '#markup' => '<div>' .
                        Link::fromTextAndUrl('Have you an external User profile Provider (Mysql, Ldap, etc ..)',
                            Url::fromUri($config->get(ObibaAgate::CONFIG_PREFIX_SERVER . '.' . 'url') . '/' .
                                $config->get(ObibaAgate::CONFIG_PREFIX_SERVER . '.' . 'sign_up_page')
                                , [
                                'attributes' => [
                                    'target' => '_blank',
                                ]
                            ])
                        )->toString() .
                        '</div>'
                ];
            }

            $form['captcha_response'] = [
                '#weight' => 25,
                '#markup' => '<div class="g-recaptcha" data-sitekey=" ' . $config->get(ObibaAgate::CONFIG_PREFIX_USER_FIELDS_MAPPING . '.' . 'drupal_profile_field.recaptcha') . ' "></div>',
            ];
            $form['captcha_response']['#attached'] = [
                'html_head' => [
                    [
                        [
                            '#tag' => 'script',
                            '#attributes' => [
                                'src' => Url::fromUri('https://www.google.com/recaptcha/api.js', ['query' => ['hl' => \Drupal::service('language_manager')->getCurrentLanguage()->getId()], 'absolute' => TRUE])->toString(),
                                'async' => TRUE,
                                'defer' => TRUE,
                            ],
                        ],
                        'recaptcha_api',
                    ],
                ],
            ];
        }
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state) {
        $account = $this->entity;
        $pass = $account->getPassword();
        $admin = $form_state->getValue('administer_users');
        $notify = !$form_state->isValueEmpty('notify');

        // Save has no return value so this cannot be tested.
        // Assume save has gone through correctly.
        $account->save();

        $form_state->set('user', $account);
        $form_state->setValue('uid', $account->id());

        $this->logger('user')->notice('New user: %name %email.', ['%name' => $form_state->getValue('name'), '%email' => '<' . $form_state->getValue('mail') . '>', 'type' => $account->toLink($this->t('Edit'), 'edit-form')->toString()]);

        // Add plain text password into user account to generate mail tokens.
        $account->password = $pass;

        // New administrative account without notification.
        if ($admin && !$notify) {
            $this->messenger()->addStatus($this->t('Created a new user account for <a href=":url">%name</a>. No email has been sent.', [':url' => $account->toUrl()->toString(), '%name' => $account->getAccountName()]));
        }
        // No email verification required; log in user immediately.
        elseif (!$admin && !\Drupal::config('user.settings')->get('verify_mail') && $account->isActive()) {
          //  user_login_finalize($account);
            $this->messenger()->addStatus($this->t('Registration successful. You are now logged in.'));
            $form_state->setRedirect('<front>');
        }
        // No administrator approval required.
        elseif ($account->isActive() || $notify) {
            if (!$account->getEmail() && $notify) {
                $this->messenger()->addStatus($this->t('The new user <a href=":url">%name</a> was created without an email address, so no welcome message was sent.', [':url' => $account->toUrl()->toString(), '%name' => $account->getAccountName()]));
            }
            else {
                    if ($notify) {
                        $this->messenger()->addStatus($this->t('A welcome message with further instructions has been emailed to the new user <a href=":url">%name</a>.', [':url' => $account->toUrl()->toString(), '%name' => $account->getAccountName()]));
                    }
                    else {
                        $this->messenger()->addStatus($this->t('A welcome message with further instructions has been sent to your email address.'));
                        $form_state->setRedirect('<front>');
                    }
            }
        }
        // Administrator approval required.
        else {
            $this->messenger()->addStatus($this->t('Thank you for applying for an account. Your account is currently pending approval by the site administrator.<br />In the meantime, a welcome message with further instructions has been sent to your email address.'));
            $form_state->setRedirect('<front>');
        }
    }

    private function hasRealmOptions(){
       return count(current(array_filter(\Drupal::service('obiba_agate.server.agateclient')->getConfigFormJoin()['definition'],
           function($definition){
               return $definition['key'] === ObibaAgate::OBIBA_AGATE_FORM_USER_FIELDS_REALM;
           }))['titleMap']) > 1;
    }
}