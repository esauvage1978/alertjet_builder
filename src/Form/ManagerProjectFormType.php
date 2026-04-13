<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

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
            ->add('imapEnabled', CheckboxType::class, [
                'label' => 'form.manager_project.imap_enabled',
                'required' => false,
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
