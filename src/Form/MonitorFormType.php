<?php

namespace App\Form;

use App\Dto\MonitorInput;
use Symfony\Component\Form\AbstractType;
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
            ->add('intervalMinutes', IntegerType::class, [
                'label' => 'Intervalle attendu (minutes)',
                'help' => 'Durée maximale entre deux pings de succès.',
            ])
            ->add('graceMinutes', IntegerType::class, [
                'label' => 'Délai de grâce (minutes)',
                'help' => 'Retard toléré avant de passer en panne et d\'envoyer une alerte.',
            ]);
    }

    public function configureOptions(OptionsResolver $pResolver): void
    {
        $pResolver->setDefaults(['data_class' => MonitorInput::class]);
    }
}
