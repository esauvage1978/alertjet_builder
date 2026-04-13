<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Util\AvatarForegroundPalette;
use App\Util\AvatarPalette;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class UserProfileFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tr = $this->translator;
        $builder
            ->add('displayName', TextType::class, [
                'label' => 'form.display_name',
                'required' => true,
                'attr' => ['autocomplete' => 'nickname', 'maxlength' => 120, 'id' => 'profile-display-name-input'],
                'constraints' => [
                    new NotBlank(message: 'validation.display_name.not_blank'),
                    new Length(max: 120, maxMessage: 'validation.display_name.max'),
                ],
            ])
            ->add('avatarInitialsCustom', TextType::class, [
                'label' => 'form.initials_custom',
                'required' => false,
                'attr' => [
                    'maxlength' => 3,
                    'id' => 'profile-initials-custom-input',
                    'autocomplete' => 'off',
                    'class' => 'profile-initials-input text-uppercase',
                ],
                'constraints' => [
                    new Length(max: 3, maxMessage: 'validation.initials_custom.max'),
                    new Regex(pattern: '/^[\p{L}\p{N}]*$/u', message: 'validation.initials_custom.invalid'),
                ],
            ])
            ->add('avatarColor', ChoiceType::class, [
                'label' => 'form.avatar_bg',
                'required' => false,
                'placeholder' => false,
                'choices' => AvatarPalette::choices(),
                'expanded' => true,
                'multiple' => false,
                'choice_label' => false,
                'attr' => ['class' => 'user-profile-palette js-profile-bg-palette'],
                'choice_attr' => static function (mixed $choice, mixed $key, mixed $value) use ($tr): array {
                    $hex = \is_string($choice) ? $choice : '';
                    $labelKey = \is_string($key) ? $key : '';
                    $title = $labelKey !== '' ? $tr->trans($labelKey, [], 'messages') : '';

                    return [
                        'class' => 'profile-color-swatch-input',
                        'style' => $hex !== '' ? 'background-color:'.$hex.';' : '',
                        'title' => $title,
                        'aria-label' => $title,
                    ];
                },
                'constraints' => [
                    new Choice(choices: AvatarPalette::allowedHexValues(), message: 'validation.avatar_color.invalid'),
                ],
            ])
            ->add('avatarForegroundColor', ChoiceType::class, [
                'label' => 'form.avatar_fg',
                'required' => false,
                'placeholder' => false,
                'choices' => AvatarForegroundPalette::choices(),
                'expanded' => true,
                'multiple' => false,
                'choice_label' => false,
                'attr' => ['class' => 'user-profile-palette js-profile-fg-palette'],
                'choice_attr' => static function (mixed $choice, mixed $key, mixed $value) use ($tr): array {
                    $hex = \is_string($choice) ? $choice : '';
                    $labelKey = \is_string($key) ? $key : '';
                    $title = $labelKey !== '' ? $tr->trans($labelKey, [], 'messages') : '';

                    return [
                        'class' => 'profile-fg-swatch-input',
                        'style' => $hex !== '' ? 'background-color:'.$hex.';border:1px solid rgba(0,0,0,.12);' : '',
                        'title' => $title,
                        'aria-label' => $title,
                    ];
                },
                'constraints' => [
                    new Choice(choices: AvatarForegroundPalette::allowedHexValues(), message: 'validation.avatar_fg.invalid'),
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $user = $event->getData();
            if (!$user instanceof User) {
                return;
            }
            if ($user->getAvatarColor() === null) {
                $user->setAvatarColor(AvatarPalette::DEFAULT_HEX);
            }
            if ($user->getAvatarForegroundColor() === null) {
                $user->setAvatarForegroundColor(AvatarForegroundPalette::DEFAULT_HEX);
            }
        });

        /* Après validation : éviter null si soumission sans sélection explicite (champs non requis). */
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            static function (FormEvent $event): void {
                $user = $event->getData();
                if (!$user instanceof User) {
                    return;
                }
                $form = $event->getForm();
                if (!$form->isRoot() || !$form->isValid()) {
                    return;
                }
                if ($user->getAvatarColor() === null || $user->getAvatarColor() === '') {
                    $user->setAvatarColor(AvatarPalette::DEFAULT_HEX);
                }
                if ($user->getAvatarForegroundColor() === null || $user->getAvatarForegroundColor() === '') {
                    $user->setAvatarForegroundColor(AvatarForegroundPalette::DEFAULT_HEX);
                }
            },
            -100,
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'messages',
        ]);
    }
}
