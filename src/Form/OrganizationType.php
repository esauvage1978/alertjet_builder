<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Organization;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class OrganizationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'form.organization_name',
            'attr' => ['class' => 'form-control', 'maxlength' => 180],
            'constraints' => [
                new NotBlank(message: 'validation.organization_name.not_blank'),
                new Length(max: 180),
            ],
        ]);

        $builder->add('billingLine1', TextType::class, [
            'label' => 'form.billing_line1',
            'required' => false,
            'attr' => ['class' => 'form-control', 'maxlength' => 255],
        ]);
        $builder->add('billingLine2', TextType::class, [
            'label' => 'form.billing_line2',
            'required' => false,
            'attr' => ['class' => 'form-control', 'maxlength' => 255],
        ]);
        $builder->add('billingPostalCode', TextType::class, [
            'label' => 'form.billing_postal_code',
            'required' => false,
            'attr' => ['class' => 'form-control', 'maxlength' => 32],
        ]);
        $builder->add('billingCity', TextType::class, [
            'label' => 'form.billing_city',
            'required' => false,
            'attr' => ['class' => 'form-control', 'maxlength' => 120],
        ]);
        $builder->add('billingCountry', CountryType::class, [
            'label' => 'form.billing_country',
            'required' => false,
            'placeholder' => 'form.billing_country_placeholder',
            'preferred_choices' => ['FR', 'BE', 'CH', 'CA', 'LU'],
            'attr' => ['class' => 'custom-select'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Organization::class,
            'translation_domain' => 'messages',
        ]);
    }
}
