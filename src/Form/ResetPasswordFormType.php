<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ResetPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'first_options' => [
                'label' => 'form.new_password',
                'attr' => ['autocomplete' => 'new-password', 'class' => 'form-input'],
            ],
            'second_options' => [
                'label' => 'form.password_confirm',
                'attr' => ['autocomplete' => 'new-password', 'class' => 'form-input'],
            ],
            'invalid_message' => 'validation.password.mismatch',
            'constraints' => [
                new NotBlank(message: 'validation.password.not_blank'),
                new Length(
                    min: 8,
                    max: 4096,
                    minMessage: 'validation.password.min_length',
                ),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'messages',
        ]);
    }
}
