<?php
declare(strict_types=1);
namespace Airship\Cabin\Bridge\Controller;

use Airship\Cabin\Bridge\Model\UserAccounts;
use Airship\Cabin\Bridge\Filter\Crew\{
    DeleteGroupFilter,
    DeleteUserFilter,
    EditGroupFilter,
    EditUserFilter,
    NewGroupFilter,
    NewUserFilter
};
use Airship\Engine\Bolt\Get;

require_once __DIR__.'/init_gear.php';

/**
 * Class Crew
 * @package Airship\Cabin\Bridge\Controller
 */
class Crew extends AdminOnly
{
    use Get;

    /**
     * @var UserAccounts
     */
    protected $account;

    /**
     * This function is called after the dependencies have been injected by
     * AutoPilot. Think of it as a user-land constructor.
     */
    public function airshipLand()
    {
        parent::airshipLand();

        $this->account = $this->model('UserAccounts');
        $this->storeViewVar('active_submenu', ['Admin', 'Crew']);
    }

    /**
     * List the main crew page
     *
     * @route crew
     */
    public function index()
    {
        $this->view('crew');
    }

    /**
     * Create a new group for users
     *
     * @route crew/groups/new
     */
    public function createGroup()
    {
        $post = $this->post(new NewGroupFilter());
        if (!empty($post)) {
            if ($this->account->createGroup($post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/crew/groups'
                );
            }
        }

        $this->view('crew/group_new', [
            'active_link' =>
                'bridge-link-admin-crew-groups',
            'groups' =>
                $this->account->getGroupTree()
        ]);
    }

    /**
     * Create a new user
     *
     * @route crew/users/new
     * @param string $userId
     */
    public function createUser(string $userId = '')
    {
        $userId = (int) $userId;
        $user = $this->account->getUserAccount($userId, true);
        $post = $this->post(new NewUserFilter());
        if ($post) {
            if (!empty($post['preferences'])) {
                if (\is_string($post['preferences'])) {
                    $post['preferences'] = \json_decode(
                        $post['preferences'],
                        true
                    );
                }
            } else {
                $post['preferences'] = [];
            }
            $userId = $this->account->createUser($post);
            if ($userId) {
                $this->account->editUserCustomFields(
                    $userId,
                    $post['custom_fields'] ?? '[]'
                );
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/crew/users'
                );
            }
        }

        $this->view(
            'crew/user_new',
            [
                'active_link' =>
                    'bridge-link-admin-crew-users',
                'user' =>
                    $user,
                'groups' =>
                    $this->account->getGroupTree()
            ]
        );
    }

    /**
     * @param string $groupId
     * @route crew/groups/edit/{id}
     */
    public function deleteGroup(string $groupId = '')
    {
        $groupId = (int) $groupId;
        $group = $this->account->getGroup($groupId);
        $post = $this->post(new DeleteGroupFilter());
        if ($post) {
            if ($this->account->deleteGroup($groupId, $post['move_children'] ?? 0)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/crew/groups'
                );
            }
        }

        $this->view('crew/group_delete', [
            'active_link' =>
                'bridge-link-admin-crew-groups',
            'group' =>
                $group,
            'allowed_parents' =>
                $this->account->getGroupTree(0, 'children', [$groupId])
        ]);
    }

    /**
     * @param string $userId
     * @route crew/users/edit/{id}
     */
    public function deleteUser(string $userId = '')
    {
        $userId = (int) $userId;
        $user = $this->account->getUserAccount($userId, true);
        $post = $this->post(new DeleteUserFilter());
        if ($post) {
            if ($this->account->deleteUser($userId)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/crew/users'
                );
            }
        }

        $this->view(
            'crew/user_delete',
            [
                'active_link' =>
                    'bridge-link-admin-crew-users',
                'user' =>
                    $user
            ]
        );
    }

    /**
     * Edit a group's information
     *
     * @route crew/groups/edit/{id}
     * @param string $groupId
     */
    public function editGroup(string $groupId = '')
    {
        $groupId = (int) $groupId;
        $post = $this->post(new EditGroupFilter());
        if (!empty($post)) {
            if ($this->account->editGroup($groupId, $post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/crew/groups'
                );
            }
        }

        $this->view(
            'crew/group_edit',
            [
                'active_link' =>
                    'bridge-link-admin-crew-groups',
                'group' =>
                    $this->account->getGroup($groupId),
                'allowed_parents' =>
                    $this->account->getGroupTree(0, 'children', [$groupId])
            ]
        );
    }

    /**
     * Edit a user's information
     *
     * @route crew/users/edit/{id}
     * @param string $userId
     */
    public function editUser(string $userId = '')
    {
        $userId = (int) $userId;
        $user = $this->account->getUserAccount($userId, true);
        $post = $this->post(new EditUserFilter());
        if ($post) {
            if ($this->account->editUser($userId, $post)) {
                \Airship\redirect(
                    $this->airship_cabin_prefix . '/crew/users'
                );
            }
        }

        $this->view(
            'crew/user_edit',
            [
                'active_link' =>
                    'bridge-link-admin-crew-users',
                'user' =>
                    $user,
                'groups' =>
                    $this->account->getGroupTree()
            ]
        );
    }

    /**
     * List the groups
     *
     * @route crew/groups
     */
    public function groups()
    {
        $this->view(
            'crew/group_list',
            [
                'active_link' =>
                    'bridge-link-admin-crew-groups',
                'groups' =>
                    $this->account->getGroupTree()
            ]
        );
    }

    /**
     * List the users
     *
     * @route crew/users
     */
    public function users()
    {
        $get = $this->httpGetParams();
        list ($offset, $limit) = $this->getOffsetAndLimit($get['page'] ?? 0);

        $suffix = '';
        $dir = 'ASC';
        if (\array_key_exists('dir', $get)) {
            if ($get['dir'] === 'DESC') {
                $dir = 'DESC';
            }
        }

        if (\array_key_exists('sort', $get)) {
            switch ($get['sort']) {
                case 'username':
                case 'display_name':
                    $suffix = \http_build_query([
                        'sort' => $get['sort'],
                        'dir' => $dir
                    ]) . '&';
                    $users = $this->account->listUsers($offset, $limit, $get['sort'], $dir);
                    break;
                default:
                    $users = $this->account->listUsers($offset, $limit);
            }
        } else {
            $users = $this->account->listUsers($offset, $limit);
        }

        $this->view('crew/user_list', [
            'active_link' =>
                'bridge-link-admin-crew-users',
            'users' => $users,
            'pagination' => [
                'base' => $this->airship_cabin_prefix . '/crew/users',
                'suffix' => '?'.$suffix.'page=',
                'count' => $this->account->numUsers(),
                'page' => (int) \ceil($offset / ($limit ?? 1)) + 1,
                'per_page' => $limit
            ]
        ]);
    }

    /**
     * Gets [offset, limit] based on configuration
     *
     * @param string $page
     * @param int $per_page
     * @return int[]
     */
    protected function getOffsetAndLimit($page = null, int $per_page = 50)
    {
        $page = (int) (!empty($page) ? $page : ($_GET['page'] ?? 0));
        if ($page < 1) {
            $page = 1;
        }
        return [($page - 1) * $per_page, $per_page];
    }
}
