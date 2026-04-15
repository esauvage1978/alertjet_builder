<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use App\Service\WebhookCorsHelper;
use Symfony\Component\Form\Exception\TransformationFailedException;

final class ManagerProjectFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Organization $organization */
        $organization = $options['organization'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'form.manager_project.name',
                'attr' => ['class' => 'form-control form-control-sm', 'maxlength' => 180],
                'constraints' => [
                    new NotBlank(message: 'validation.project_name.not_blank'),
                    new Length(max: 180, maxMessage: 'validation.project_name.max'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'form.manager_project.description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'rows' => 4,
                ],
                'constraints' => [
                    new Length(max: 20000, maxMessage: 'validation.project_description.max'),
                ],
            ])
            ->add('accentColor', TextType::class, [
                'label' => 'form.manager_project.accent_bg_color',
                'required' => true,
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'maxlength' => 7,
                    'pattern' => '^#[0-9A-Fa-f]{6}$',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(message: 'validation.project_accent_color.not_blank'),
                    new Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'validation.project_accent_color.invalid'),
                ],
            ])
            ->add('accentTextColor', TextType::class, [
                'label' => 'form.manager_project.accent_text_color',
                'required' => true,
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'maxlength' => 7,
                    'pattern' => '^#[0-9A-Fa-f]{6}$',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(message: 'validation.project_accent_text_color.not_blank'),
                    new Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'validation.project_accent_text_color.invalid'),
                ],
            ])
            ->add('accentBorderColor', TextType::class, [
                'label' => 'form.manager_project.accent_border_color',
                'required' => true,
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'maxlength' => 7,
                    'pattern' => '^#[0-9A-Fa-f]{6}$',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(message: 'validation.project_accent_border_color.not_blank'),
                    new Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'validation.project_accent_border_color.invalid'),
                ],
            ])
            ->add('ticketHandlers', EntityType::class, [
                'class' => User::class,
                'label' => false,
                'choice_label' => static fn (User $u): string => $u->getDisplayNameForGreeting().' ('.$u->getEmail().')',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => static fn (UserRepository $repository) => $repository->createQueryBuilderMembersOfOrganization($organization),
                'attr' => ['class' => 'pe-handlers-form-widget'],
            ])
            ->add('slaAckTargetMinutes', IntegerType::class, [
                'label' => 'form.manager_project.sla_ack',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 1, 'placeholder' => '60'],
                'constraints' => [
                    new Range(min: 1, max: 525600, notInRangeMessage: 'validation.sla_minutes.range'),
                ],
            ])
            ->add('slaResolveTargetMinutes', IntegerType::class, [
                'label' => 'form.manager_project.sla_resolve',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 1, 'placeholder' => '480'],
                'constraints' => [
                    new Range(min: 1, max: 525600, notInRangeMessage: 'validation.sla_minutes.range'),
                ],
            ])
            ->add('slaIncidentAckTargetMinutes', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 1, 'placeholder' => '120'],
                'constraints' => [
                    new Range(min: 1, max: 525600, notInRangeMessage: 'validation.sla_minutes.range'),
                ],
            ])
            ->add('slaIncidentResolveTargetMinutes', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 1, 'placeholder' => '2880'],
                'constraints' => [
                    new Range(min: 1, max: 525600, notInRangeMessage: 'validation.sla_minutes.range'),
                ],
            ])
            ->add('slaProblemAckTargetMinutes', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 1],
                'constraints' => [
                    new Range(min: 1, max: 525600, notInRangeMessage: 'validation.sla_minutes.range'),
                ],
            ])
            ->add('slaProblemResolveTargetMinutes', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 1],
                'constraints' => [
                    new Range(min: 1, max: 525600, notInRangeMessage: 'validation.sla_minutes.range'),
                ],
            ])
            ->add('slaRequestAckTargetMinutes', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 1],
                'constraints' => [
                    new Range(min: 1, max: 525600, notInRangeMessage: 'validation.sla_minutes.range'),
                ],
            ])
            ->add('slaRequestResolveTargetMinutes', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 1],
                'constraints' => [
                    new Range(min: 1, max: 525600, notInRangeMessage: 'validation.sla_minutes.range'),
                ],
            ])
            ->add('autoCloseResolvedAfterHours', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 0, 'placeholder' => '48'],
                'constraints' => [
                    new Range(min: 0, max: 8760, notInRangeMessage: 'validation.sla_minutes.range'),
                ],
            ])
            ->add('imapEnabled', CheckboxType::class, [
                'label' => 'form.manager_project.imap_enabled',
                'required' => false,
                /** POST JSON / SPA : on peut envoyer « 0 » pour décocher explicitement */
                'false_values' => [null, false, '', '0'],
            ])
            ->add('webhookIntegrationEnabled', CheckboxType::class, [
                'label' => 'form.manager_project.webhook_integration_enabled',
                'required' => false,
                'false_values' => [null, false, '', '0'],
            ])
            ->add('phoneIntegrationEnabled', CheckboxType::class, [
                'label' => 'form.manager_project.phone_integration_enabled',
                'required' => false,
                'false_values' => [null, false, '', '0'],
            ])
            ->add('internalFormIntegrationEnabled', CheckboxType::class, [
                'label' => 'form.manager_project.internal_form_integration_enabled',
                'required' => false,
                'false_values' => [null, false, '', '0'],
            ])
            ->add('phoneNumber', TextType::class, [
                'label' => 'form.manager_project.phone_number',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'maxlength' => 48,
                    'placeholder' => '+33 1 23 45 67 89',
                    'autocomplete' => 'tel',
                    'inputmode' => 'tel',
                ],
                'constraints' => [
                    new Length(max: 48, maxMessage: 'validation.project_phone.max'),
                    new Callback(static function (?string $value, ExecutionContextInterface $context): void {
                        $root = $context->getRoot();
                        $data = $root->getData();
                        if (!$data instanceof Project) {
                            return;
                        }
                        if (!$data->isPhoneIntegrationEnabled()) {
                            return;
                        }
                        if ($value === null || trim($value) === '') {
                            $context->buildViolation('validation.project_phone.not_blank')->addViolation();
                        }
                    }),
                ],
            ])
            ->add('emergencyPhone', TextType::class, [
                'label' => 'form.manager_project.emergency_phone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'maxlength' => 255,
                    'placeholder' => 'Optionnel — numéro ou consigne',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new Length(max: 255, maxMessage: 'validation.project_emergency_phone.max'),
                ],
            ])
            ->add('phoneSchedule', HiddenType::class, [
                'label' => false,
                'required' => false,
            ])
            ->add('webhookCorsAllowedOrigins', TextareaType::class, [
                'label' => 'form.manager_project.webhook_cors_allowed_origins',
                'required' => false,
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'rows' => 4,
                    'placeholder' => 'https://app.exemple.fr',
                    'spellcheck' => 'false',
                ],
                'help' => 'form.manager_project.webhook_cors_allowed_origins_help',
                'constraints' => [
                    new Callback(static function (?string $value, ExecutionContextInterface $context): void {
                        $bad = WebhookCorsHelper::invalidOriginLines($value);
                        foreach ($bad as $line) {
                            $context->buildViolation('validation.webhook_cors.invalid_line')
                                ->setParameter('%line%', $line)
                                ->addViolation();
                        }
                    }),
                ],
            ])
            ->add('imapHost', TextType::class, [
                'label' => 'form.manager_project.imap_host',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'placeholder' => 'imap.exemple.fr', 'autocomplete' => 'off'],
            ])
            ->add('imapPort', IntegerType::class, [
                'label' => 'form.manager_project.imap_port',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'min' => 1, 'max' => 65535],
                'constraints' => [
                    new Range(min: 1, max: 65535, notInRangeMessage: 'validation.imap_port.range'),
                ],
            ])
            ->add('imapTls', CheckboxType::class, [
                'label' => 'form.manager_project.imap_tls',
                'required' => false,
                'false_values' => [null, false, '', '0'],
            ])
            ->add('imapUsername', TextType::class, [
                'label' => 'form.manager_project.imap_username',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'autocomplete' => 'username'],
            ])
            ->add('imapPassword', PasswordType::class, [
                'label' => 'form.manager_project.imap_password',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control form-control-sm',
                    'autocomplete' => 'new-password',
                ],
            ])
            ->add('imapMailbox', TextType::class, [
                'label' => 'form.manager_project.imap_mailbox',
                'required' => false,
                'attr' => ['class' => 'form-control form-control-sm', 'placeholder' => 'INBOX'],
            ])
            ->add('_active_tab', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'data' => 'pe-pane-general',
                'attr' => ['class' => 'pe-active-tab-field'],
            ]);

        $builder->get('phoneSchedule')->addModelTransformer(new CallbackTransformer(
            // array|null -> string (JSON) for the hidden input
            static function (mixed $value): string {
                if ($value === null || $value === '' || $value === []) {
                    return '';
                }
                if (\is_array($value)) {
                    try {
                        return json_encode($value, JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        return '';
                    }
                }

                return '';
            },
            // string -> array|null for the entity
            static function (mixed $value): ?array {
                if ($value === null || $value === '') {
                    return null;
                }
                if (!\is_string($value)) {
                    throw new TransformationFailedException('phoneSchedule must be a string.');
                }
                $raw = trim($value);
                if ($raw === '') {
                    return null;
                }
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    throw new TransformationFailedException('Invalid phoneSchedule JSON.');
                }
                if (!\is_array($decoded)) {
                    throw new TransformationFailedException('Invalid phoneSchedule payload.');
                }

                return $decoded;
            },
        ));

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }
            if (!isset($data['imapPort']) || $data['imapPort'] === '' || $data['imapPort'] === null) {
                $data['imapPort'] = 993;
            }
            $mb = $data['imapMailbox'] ?? '';
            if (!\is_string($mb) || trim($mb) === '') {
                $data['imapMailbox'] = 'INBOX';
            }
            if (($data['slaAckTargetMinutes'] ?? '') === '') {
                $data['slaAckTargetMinutes'] = null;
            }
            if (($data['slaResolveTargetMinutes'] ?? '') === '') {
                $data['slaResolveTargetMinutes'] = null;
            }
            foreach ([
                'slaIncidentAckTargetMinutes',
                'slaIncidentResolveTargetMinutes',
                'slaProblemAckTargetMinutes',
                'slaProblemResolveTargetMinutes',
                'slaRequestAckTargetMinutes',
                'slaRequestResolveTargetMinutes',
            ] as $k) {
                if (($data[$k] ?? '') === '') {
                    $data[$k] = null;
                }
            }
            if (($data['autoCloseResolvedAfterHours'] ?? '') === '') {
                unset($data['autoCloseResolvedAfterHours']);
            }
            if (isset($data['description']) && \is_string($data['description']) && trim($data['description']) === '') {
                $data['description'] = null;
            }
            foreach (['accentColor', 'accentTextColor', 'accentBorderColor'] as $accentKey) {
                if (isset($data[$accentKey]) && \is_string($data[$accentKey])) {
                    $ac = trim($data[$accentKey]);
                    if ($ac !== '' && !str_starts_with($ac, '#')) {
                        $data[$accentKey] = '#'.$ac;
                    }
                }
            }
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'translation_domain' => 'messages',
        ]);
        $resolver->setRequired('organization');
        $resolver->setAllowedTypes('organization', Organization::class);
    }
}
