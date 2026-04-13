<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'attr' => ['autocomplete' => 'email', 'class' => 'form-input'],
                'constraints' => [
                    new NotBlank(message: 'validation.email.not_blank'),
                    new Email(message: 'validation.email.invalid'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'form.password',
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
                        maxMessage: 'validation.password.max_length',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'messages',
        ]);
    }
}
