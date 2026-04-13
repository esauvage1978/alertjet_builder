<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organization;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AdminUserFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.admin_user.email',
                'attr' => ['autocomplete' => 'off', 'class' => 'form-control', 'maxlength' => 180],
                'constraints' => [
                    new NotBlank(message: 'validation.email.not_blank'),
                    new Email(message: 'validation.email.invalid'),
                ],
            ])
            ->add('displayName', TextType::class, [
                'label' => 'form.display_name',
                'required' => false,
                'attr' => ['autocomplete' => 'off', 'class' => 'form-control', 'maxlength' => 120],
            ])
            ->add('primaryRole', ChoiceType::class, [
                'mapped' => false,
                'label' => 'form.admin_user.primary_role',
                'data' => 'ROLE_USER',
                'choices' => [
                    'form.admin_user.role_admin' => 'ROLE_ADMIN',
                    'form.admin_user.role_manager' => 'ROLE_GESTIONNAIRE',
                    'form.admin_user.role_user' => 'ROLE_USER',
                    'form.admin_user.role_client' => 'ROLE_CLIENT',
                ],
                'placeholder' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('organizations', EntityType::class, [
                'class' => Organization::class,
                'label' => 'form.admin_user.organizations',
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'query_builder' => static fn ($repository) => $repository->createQueryBuilder('o')->orderBy('o.name', 'ASC'),
                'attr' => ['class' => 'form-control', 'size' => 8],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'form.password',
                    'attr' => ['autocomplete' => 'new-password', 'class' => 'form-control'],
                ],
                'second_options' => [
                    'label' => 'form.password_confirm',
                    'attr' => ['autocomplete' => 'new-password', 'class' => 'form-control'],
                ],
                'invalid_message' => 'validation.password.mismatch',
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $user = $event->getData();
            if (!$user instanceof User || $user->getId() === null) {
                return;
            }
            $form = $event->getForm();
            $form->get('primaryRole')->setData(self::primaryRoleFromRoles($user->getRoles()));
        });

        $translator = $this->translator;
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($translator): void {
            $form = $event->getForm();
            $requirePassword = $form->getConfig()->getOption('require_password');
            $passField = $form->get('plainPassword');
            $plain = $passField->getData();
            $plainStr = \is_string($plain) ? $plain : '';
            if ($requirePassword) {
                if ($plainStr === '') {
                    $passField->get('first')->addError(new FormError(
                        $translator->trans('validation.password.not_blank', [], 'validators'),
                    ));

                    return;
                }
            }
            if ($plainStr !== '' && \strlen($plainStr) < 8) {
                $passField->get('first')->addError(new FormError(
                    $translator->trans('admin.user.error.password_min', [], 'messages'),
                ));
            }
        });
    }

    /**
     * @param list<string> $roles
     */
    public static function primaryRoleFromRoles(array $roles): string
    {
        if (\in_array('ROLE_ADMIN', $roles, true)) {
            return 'ROLE_ADMIN';
        }
        if (\in_array('ROLE_GESTIONNAIRE', $roles, true)) {
            return 'ROLE_GESTIONNAIRE';
        }
        if (\in_array('ROLE_CLIENT', $roles, true)) {
            return 'ROLE_CLIENT';
        }

        return 'ROLE_USER';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'messages',
            'require_password' => true,
        ]);
        $resolver->setAllowedTypes('require_password', 'bool');
    }
}
