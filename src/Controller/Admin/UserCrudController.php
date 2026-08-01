<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserCrudController extends AbstractCrudController
{
    private const AVAILABLE_ROLES = [
        'Администратор' => 'ROLE_ADMIN',
        'Менеджер' => 'ROLE_MANAGER',
        'Оператор' => 'ROLE_OPERATOR',
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Пользователь')
            ->setEntityLabelInPlural('Пользователи')
            ->setEntityPermission('ROLE_ADMIN')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields([
                'email',
                'fullName',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Основное')
            ->onlyOnForms();

        yield IdField::new('id', 'ID')
            ->onlyOnDetail();

        yield EmailField::new('email', 'Email');

        yield TextField::new('fullName', 'ФИО');

        yield ChoiceField::new('roles', 'Роли')
            ->setChoices(self::AVAILABLE_ROLES)
            ->allowMultipleChoices()
            ->renderExpanded(false);

        yield BooleanField::new('isActive', 'Активен');

        $passwordField = TextField::new('plainPassword', 'Пароль')
            ->onlyOnForms()
            ->setFormType(PasswordType::class)
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('required', $pageName === Crud::PAGE_NEW);

        if ($pageName === Crud::PAGE_EDIT) {
            $passwordField->setHelp('Оставьте пустым, если пароль менять не нужно.');
        }

        yield $passwordField;

        yield DateTimeField::new('lastLoginAt', 'Последний вход')
            ->hideOnForm()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('createdAt', 'Создан')
            ->hideOnForm()
            ->setFormat('dd.MM.yyyy HH:mm:ss');

        yield DateTimeField::new('updatedAt', 'Обновлен')
            ->hideOnForm()
            ->setFormat('dd.MM.yyyy HH:mm:ss');
    }

    /**
     * При создании пользователя plainPassword приходит из формы как unmapped field.
     * Перед сохранением превращаем его в хеш и кладем в User::password.
     */
    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }

        $this->hashPlainPasswordIfProvided($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * При редактировании пароль меняем только если администратор ввел новый.
     */
    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }

        $this->hashPlainPasswordIfProvided($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPlainPasswordIfProvided(User $user): void
    {
        $plainPassword = $this->getContext()?->getRequest()->request->all('User')['plainPassword'] ?? null;

        if (!is_string($plainPassword) || $plainPassword === '') {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
    }
}
