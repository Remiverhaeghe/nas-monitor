<?php

namespace App\Form;

use App\Dto\MonitorInput;
use App\Entity\Monitor;
use App\Enum\MonitorType;
use App\Enum\NotificationChannel;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
                'help' => 'Site web : URL complète. Port TCP et certificat TLS : hôte:port. DNS : nom, ou nom=adresse attendue. Ping : hôte ou adresse IPv4. Tâche planifiée : vide.',
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
            ])
            ->add('parent', EntityType::class, [
                'class' => Monitor::class,
                'label' => 'Dépend de',
                'required' => false,
                'placeholder' => 'Aucun',
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $pRepository): QueryBuilder => $pRepository->createQueryBuilder('m')
                    ->where('m.parent IS NULL')
                    ->andWhere('m.id <> :current')
                    ->setParameter('current', $pOptions['current_monitor']?->getId() ?? 0)
                    ->orderBy('m.name'),
                'help' => 'Par exemple le NAS pour un service qu\'il héberge : tant que le NAS est en panne, les alertes de ce moniteur attendent.',
            ])
            ->add('publicVisible', CheckboxType::class, [
                'label' => 'Visible sur la page de statut publique',
                'required' => false,
            ])
            ->add('metricLimits', TextType::class, [
                'label' => 'Seuils des métriques',
                'required' => false,
                'help' => 'Tâche planifiée qui envoie des métriques : maximum par métrique, par exemple disk_used_percent=90, temperature_c=60.',
            ]);
    }

    public function configureOptions(OptionsResolver $pResolver): void
    {
        $pResolver->setDefaults([
            'data_class' => MonitorInput::class,
            'is_edit' => false,
            'current_monitor' => null,
        ]);
        $pResolver->setAllowedTypes('is_edit', 'bool');
        $pResolver->setAllowedTypes('current_monitor', ['null', Monitor::class]);
    }
}
