<?php

namespace App\Form;

use App\Dto\MaintenanceInput;
use App\Entity\Monitor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Saisie à l'heure d'affichage, stockage en UTC.
 *
 * @extends AbstractType<MaintenanceInput>
 */
final class MaintenanceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $pBuilder, array $pOptions): void
    {
        $vDateOptions = [
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'model_timezone' => 'UTC',
            'view_timezone' => $pOptions['display_timezone'],
        ];

        $pBuilder
            ->add('monitor', EntityType::class, [
                'class' => Monitor::class,
                'choice_label' => 'name',
                'label' => 'Moniteur',
                'required' => false,
                'placeholder' => 'Tous les moniteurs',
            ])
            ->add('startsAt', DateTimeType::class, ['label' => 'Début'] + $vDateOptions)
            ->add('endsAt', DateTimeType::class, ['label' => 'Fin'] + $vDateOptions)
            ->add('reason', TextType::class, [
                'label' => 'Motif',
                'help' => 'Par exemple : mise à jour du NAS, remplacement d\'un disque.',
            ]);
    }

    public function configureOptions(OptionsResolver $pResolver): void
    {
        $pResolver->setDefaults(['data_class' => MaintenanceInput::class]);
        $pResolver->setRequired('display_timezone');
        $pResolver->setAllowedTypes('display_timezone', 'string');
    }
}
