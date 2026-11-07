<?php

namespace App\Form;

use App\Dto\MonitorInput;
use App\Enum\MonitorType;
use App\Enum\NotificationChannel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<MonitorInput>
 */
final class MonitorFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $pBuilder, array $pOptions): void
    {
        $pBuilder
            ->add('name', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('type', EnumType::class, [
                'class' => MonitorType::class,
                'label' => 'Type',
                'choice_label' => static fn (MonitorType $pType): string => $pType->label(),
                'disabled' => $pOptions['is_edit'],
                'help' => $pOptions['is_edit'] ? 'Le type ne se modifie pas après la création.' : null,
            ])
            ->add('target', TextType::class, [
                'label' => 'Cible',
                'required' => false,
                'help' => 'Site web : URL complète. Port TCP et certificat TLS : hôte:port. Tâche planifiée : vide.',
            ])
            ->add('groupName', TextType::class, [
                'label' => 'Groupe',
                'required' => false,
                'help' => 'Regroupe les moniteurs sur le tableau de bord, par exemple Sauvegardes ou Services.',
            ])
            ->add('intervalMinutes', IntegerType::class, [
                'label' => 'Intervalle (minutes)',
                'help' => 'Tâche planifiée : durée maximale entre deux pings de succès. Contrôle actif : fréquence du contrôle.',
            ])
            ->add('graceMinutes', IntegerType::class, [
                'label' => 'Délai de grâce (minutes)',
                'help' => 'Retard toléré avant de passer en panne et d\'envoyer une alerte.',
            ])
            ->add('notificationChannel', EnumType::class, [
                'class' => NotificationChannel::class,
                'label' => 'Alertes',
                'choice_label' => static fn (NotificationChannel $pChannel): string => $pChannel->label(),
            ]);
    }

    public function configureOptions(OptionsResolver $pResolver): void
    {
        $pResolver->setDefaults([
            'data_class' => MonitorInput::class,
            'is_edit' => false,
        ]);
        $pResolver->setAllowedTypes('is_edit', 'bool');
    }
}
