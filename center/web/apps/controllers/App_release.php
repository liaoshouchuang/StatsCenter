<?php
namespace App\Controller;

class App_release extends \App\LoginController
{
    function app_list()
    {
        $query_params = [
            'page' => intval(array_get($_GET, 'page', 1)),
            'pagesize' => 15,
            'order' => 'id desc',
            'where' => '`enable` = ' . APP_STATUS_ENABLED,
        ];
        $data = table('app', 'platform')->gets($query_params, $pager);

        $os_list = model('App')->getOSList();
        foreach ($data as &$row)
        {
            if (isset($os_list[$row['os']]))
            {
                $row['os_name'] = $os_list[$row['os']];
            }
            else
            {
                $row['os_name'] = \Swoole::$php->config['setting']['app_os_name'][APP_OS_UNKNOWN];
                $row['os'] = APP_OS_UNKNOWN;
            }
        }
        unset($row);

        $this->assign('pager', $pager);
        $this->assign('data', $data);
        $this->display();
    }

    function release_list()
    {
        $app_id = !empty($_GET['app_id']) ? intval($_GET['app_id']) : null;
        if (!is_null($app_id))
        {
            $app = table('app', 'platform')->get($app_id)->get();
        }
        if (empty($app))
        {
            return $this->error('APP不存在！');
        }

        $query_params = [
            'where' => 'app_id = {$app_id}',
            'page' => intval(array_get($_GET, 'page', 1)),
            'pagesize' => 15,
            'order' => 'version_int DESC, id DESC',
        ];
        $data = table('app_release', 'platform')->gets($query_params, $pager);
        $release_id_list = [];

        if (!empty($data))
        {
            foreach ($data as &$row)
            {
                $release_id_list[] = intval($row['id']);
            }
            unset($row);

            $query_params = [
                'select' => 'app_release_link.*, app_channel.name AS channel_name',
                'order' => 'app_release_link.channel_id DESC',
                'where' => "app_release_link.app_id = {$app_id} AND app_release_link.release_id IN (" . implode(',', $release_id_list) . ')',
                'leftjoin' => ['app_channel', 'app_release_link.channel_id = app_channel.id'],
            ];
            $link_list = table('app_release_link', 'platform')->gets($query_params);

            if (!empty($link_list))
            {
                $temp_link_list = [];
                foreach ($link_list as &$row)
                {
                    $row['release_id'] = intval($row['release_id']);
                    if (!isset($temp_link_list[$row['release_id']]))
                    {
                        $temp_link_list[$row['release_id']] = [];
                    }
                    $temp_link_list[$row['release_id']][] = $row;
                }
                unset($row);
                $link_list = $temp_link_list;

                foreach ($data as &$row)
                {
                    if (isset($link_list[$row['id']]))
                    {
                        $row['release_link_list'] = $link_list[$row['id']];
                    }
                }
                unset($row);
            }
        }

        $this->assign('pager', $pager);
        $this->assign('app', $app);
        $this->assign('data', $data);
        $this->display();
    }

    function new_release()
    {
        $app_id = !empty($_GET['app_id']) ? intval($_GET['app_id']) : null;
        if (!is_null($app_id))
        {
            $app = table('app', 'platform')->get($app_id)->get();
        }
        if (empty($app))
        {
            return $this->error('APP不存在！');
        }

        if (!empty($_POST))
        {
            $form_data = $_POST;
            $form_data['app_id'] = $app_id;
            $db_data = $this->validate($form_data, [$this, 'editReleaseCheck'], $errors);
            if (empty($errors))
            {
                $db_data['app_id'] = $app_id;
                $db_data['create_time'] = $db_data['update_time'] = date('Y-m-d H:i:s');
                $insert_id = table('app_release', 'platform')->put($db_data);
                if ($insert_id)
                {
                    \App\Session::flash('msg', '添加APP版本成功！');
                    return $this->redirect("/app_release/edit_release?id={$insert_id}");
                }
                else
                {
                    $errors[] = '添加失败，请联系管理员！';
                }
            }
        }

        $this->assign('app', $app);
        $this->assign('form_data', !empty($form_data) ? $form_data : []);
        $this->assign('errors', !empty($errors) ? $errors : []);
        $this->display('app_release/edit_release.php');
    }

    function edit_release()
    {
        $release_id = !empty($_GET['id']) ? intval($_GET['id']) : null;
        if (!is_null($release_id))
        {
            $release = table('app_release', 'platform')->get($release_id)->get();
        }
        if (empty($release))
        {
            return $this->error('APP版本不存在！');
        }
        $app_id = intval($release['app_id']);
        $app = table('app', 'platform')->get($app_id)->get();
        if (empty($app))
        {
            return $this->error('APP不存在，请联系管理员！');
        }

        if (!empty($_POST))
        {
            $form_data = $_POST;
            $form_data['app_id'] = $app_id;
            $db_data = $this->validate($form_data, [$this, 'editReleaseCheck'], $errors);
            if (empty($errors))
            {
                $db_data['update_time'] = date('Y-m-d H:i:s');
                $result = table('app_release', 'platform')->set($release_id, $db_data);
                if ($result)
                {
                    \App\Session::flash('msg', '编辑APP版本成功！');
                    return $this->redirect("/app_release/edit_release?id={$release_id}");
                }
                else
                {
                    $errors[] = '编辑失败，请联系管理员！';
                }
            }
        }
        else
        {
            $form_data = $release;
            if ($form_data['force_upgrade'])
            {
                $form_data['force_upgrade_stategy'] = APP_FORCE_UPGRADE_STRATEGY_ALL;
            }
        }

        $this->assign('app', $app);
        $this->assign('form_data', $form_data);
        $this->assign('msg', \App\Session::get('msg'));
        $this->assign('errors', !empty($errors) ? $errors : []);
        $this->display('app_release/edit_release.php');
    }

    function add_channel_release_link()
    {
        $release_id = !empty($_GET['release_id']) ? intval($_GET['release_id']) : null;
        if (!is_null($release_id))
        {
            $release = table('app_release', 'platform')->get($release_id)->get();
        }
        if (empty($release))
        {
            return $this->error('APP版本不存在！');
        }
        $app_id = intval($release['app_id']);
        $app = table('app', 'platform')->get($app_id)->get();
        if (empty($app))
        {
            return $this->error('APP不存在，请联系管理员！');
        }

        $query_params = [
            'page' => intval(array_get($_GET, 'page', 1)),
            'pagesize' => 15,
            'order' => 'id desc',
        ];
        $channel_list = table('app_channel', 'platform')->gets($query_params, $pager);
        if (empty($channel_list))
        {
            return $this->error('APP渠道为空，<a href="/app_release/add_channel">点这里新增APP渠道</a>！');
        }
        $form_data['channel_list'] = [];
        foreach ($channel_list as $channel)
        {
            $form_data['channel_list'][$channel['id']] = $channel['name'];
        }

        if (!empty($_POST))
        {
            $form_data = array_merge($form_data, $_POST);
            $data = $this->validate($form_data, [$this, 'editChannelReleaseLinkCheck'], $errors);
            if (empty($errors))
            {
                $data['create_time'] = $data['update_time'] = date('Y-m-d H:i:s');
                $data['app_id'] = $app_id;
                $data['release_id'] = $release_id;
                $insert_id = table('app_release_link', 'platform')->put($data);
                if ($insert_id)
                {
                    \App\Session::flash('msg', '添加渠道包成功！');
                    return $this->redirect("/app_release/edit_channel_release_link?id={$insert_id}");
                }
                else
                {
                    $errors[] = '添加失败，请联系管理员！';
                }
            }
        }

        $this->assign('app', $app);
        $this->assign('release', $release);
        $this->assign('form_data', !empty($form_data) ? $form_data : []);
        $this->assign('errors', !empty($errors) ? $errors : []);
        $this->display('app_release/edit_channel_release_link.php');
    }

    function edit_channel_release_link()
    {
        $release_link_id = !empty($_GET['id']) ? intval($_GET['id']) : null;
        if (!is_null($release_link_id))
        {
            $release_link = table('app_release_link', 'platform')->get($release_link_id)->get();
        }
        if (empty($release_link))
        {
            return $this->error('APP渠道包不存在！');
        }
        $release_id = intval($release_link['release_id']);
        $release = table('app_release', 'platform')->get($release_id)->get();
        if (empty($release))
        {
            return $this->error('APP版本不存在！');
        }
        $app_id = intval($release_link['app_id']);
        $app = table('app', 'platform')->get($app_id)->get();
        if (empty($app))
        {
            return $this->error('APP不存在，请联系管理员！');
        }

        if (!empty($_POST))
        {
            $form_data = $_POST;
            $form_data['app_channel'] = intval($release_link['channel_id']);
            $data = $this->validate($form_data, [$this, 'editChannelReleaselinkCheck'], $errors);
            if (empty($errors))
            {
                $data['update_time'] = date('Y-m-d H:i:s');
                $result = table('app_release_link', 'platform')->set($release_link_id, $data);
                if ($result)
                {
                    \App\Session::flash('msg', '编辑APP渠道包成功！');
                    return $this->redirect("/app_release/edit_channel_release_link?id={$release_link_id}");
                }
                else
                {
                    $errors[] = '编辑失败，请联系管理员！';
                }
            }
        }
        else
        {
            $form_data = $release_link;
        }

        $this->assign('app', $app);
        $this->assign('release', $release);
        $this->assign('form_data', $form_data);
        $this->assign('msg', \App\Session::get('msg'));
        $this->assign('errors', !empty($errors) ? $errors : []);
        $this->display('app_release/edit_channel_release_link.php');
    }

    function channel_list()
    {
        $query_params = [
            'page' => intval(array_get($_GET, 'page', 1)),
            'pagesize' => 15,
            'order' => 'id desc',
        ];
        $data = table('app_channel', 'platform')->gets($query_params, $pager);

        $this->assign('pager', $pager);
        $this->assign('data', $data);
        $this->display();
    }

    function add_channel()
    {
        if (!empty($_POST))
        {
            $form_data = $_POST;
            $db_data = $this->validate($form_data, [$this, 'editChannelCheck'], $errors);
            if (empty($errors))
            {
                $db_data['create_time'] = $db_date['update_time'] = date('Y-m-d H:i:s');
                $insert_id = table('app_channel', 'platform')->put($db_data);
                if ($insert_id)
                {
                    \App\Session::flash('msg', '添加渠道成功！');
                    return $this->redirect("/app_release/edit_channel?id={$insert_id}");
                }
                else
                {
                    $errors[] = '添加失败，请联系管理员！';
                }
            }
        }

        $this->assign('form_data', !empty($form_data) ? $form_data : []);
        $this->assign('errors', !empty($errors) ? $errors : []);
        $this->display('app_release/edit_channel.php');
    }

    function edit_channel()
    {
        $id = !empty($_GET['id']) ? intval($_GET['id']) : null;
        if (!is_null($id))
        {
            $channel = table('app_channel', 'platform')->get($id)->get();
        }
        if (empty($channel))
        {
            return $this->error('APP渠道不存在！');
        }

        if (!empty($_POST))
        {
            $form_data = $_POST;
            $form_data['channel'] = $channel;
            $db_data = $this->validate($form_data, [$this, 'editChannelCheck'], $errors);
            if (empty($errors))
            {
                $db_data['update_time'] = date('Y-m-d H:i:s');
                $result = table('app_channel', 'platform')->set($id, $db_data);
                if ($result)
                {
                    \App\Session::flash('msg', '编辑APP渠道成功！');
                    return $this->redirect("/app_release/edit_channel?id={$id}");
                }
                else
                {
                    $errors[] = '编辑失败，请联系管理员！';
                }
            }
        }
        else
        {
            $form_data = $channel;
        }

        $this->assign('form_data', $form_data);
        $this->assign('msg', \App\Session::get('msg'));
        $this->assign('errors', !empty($errors) ? $errors : []);
        $this->display('app_release/edit_channel.php');
    }

    function delete_channel()
    {
        exit('wait');
    }

    function editReleaseCheck($data, &$errors)
    {
        $version_number = trim(array_get($data, 'version_number'));
        if (preg_match('/^(\d+)\.(\d+).(\d+)$/', $version_number, $matches))
        {
            $version_high = (int) $matches[1];
            $version_middle = (int) $matches[2];
            $version_low = (int) $matches[3];

            if (($version_high < 0 || $version_high > 255)
                || ($version_middle < 0 || $version_middle > 255)
                || ($version_low < 0 || $version_low > 255))
            {
                $errors[] = '各位版本号取值范围0-255！';
            }
            else
            {
                $db_data['version_number'] = sprintf('%d.%d.%d', $version_high, $version_middle, $version_low);
                $db_data['version_int'] = version_string_to_int($db_data['version_number']);
            }
        }
        else
        {
            $errors[] = 'APP版本号格式不正确！';
        }
        $db_data['prompt_title'] = trim(array_get($data, 'prompt_title'));
        if ($db_data['prompt_title'] === '')
        {
            $errors[] = '弹框标题不能为空！';
        }
        $db_data['prompt_content'] = trim(array_get($data, 'prompt_content'));
        if ($db_data['prompt_content'] === '')
        {
            $errors[] = '弹框内容不能为空！';
        }
        $db_data['prompt_interval'] = trim(array_get($data, 'prompt_interval'));
        if ((!is_numeric($db_data['prompt_interval'])) || ($db_data['prompt_interval'] < 0))
        {
            $errors[] = '弹框提示周期必须为非负数';
        }
        $db_data['prompt_interval'] = intval($db_data['prompt_interval']);
        $db_data['prompt_confirm_button_text'] = trim(array_get($data, 'prompt_confirm_button_text'));
        if ($db_data['prompt_confirm_button_text'] === '')
        {
            $errors[] = '弹框确定按钮文字不能为空！';
        }
        $db_data['prompt_cancel_button_text'] = trim(array_get($data, 'prompt_cancel_button_text'));
        if ($db_data['prompt_cancel_button_text'] === '')
        {
            $errors[] = '弹框取消按钮文字不能为空！';
        }
        $force_upgrade_strategy = array_get($data, 'force_upgrade_strategy');
        if (($force_upgrade_strategy === '')
            || (!in_array($force_upgrade_strategy, [APP_FORCE_UPGRADE_STRATEGY_ALL, APP_FORCE_UPGRADE_STRATEGY_PREVIOUS])))
        {
            $errors[] = '必须指定强制更新策略！';
        }
        $force_upgrade_strategy = intval($force_upgrade_strategy);
        $db_data['force_upgrade'] = 0;
        if ($force_upgrade_strategy === APP_FORCE_UPGRADE_STRATEGY_ALL)
        {
            $db_data['force_upgrade'] = 1;
        }
        elseif ($force_upgrade_strategy === APP_FORCE_UPGRADE_STRATEGY_PREVIOUS)
        {
            // 只在version_int不为空的情况才查询数据库
            if (isset($db_data['version_int']))
            {
                $data['app_id'] = intval($data['app_id']);
                $query_params = [
                    'where' => "`version_int` < {$db_data['version_int']} AND `app_id` = {$data['app_id']}",
                    'order' => '`version_int` DESC',
                ];
                $release_list = table('app_release', 'platform')->gets($query_params, $pager);
                if (!empty($release_list))
                {
                    $release = reset($release_list);
                    $db_data['force_upgrade_version'] = $release['version_number'];
                }
                else
                {
                    $errors[] = '找不到APP上个版本！';
                }
            }
        }
        return $db_data;
    }

    function editChannelCheck($data, &$errors)
    {
        $db_data['name'] = trim(array_get($data, 'name'));
        if ($db_data['name'] === '')
        {
            $errors[] = '渠道名称不能为空！';
        }
        $db_data['channel_key'] = trim(array_get($data, 'channel_key'));
        if ($db_data['channel_key'] === '')
        {
            $errors[] = '渠道标识符不能为空！';
        }
        if (!preg_match('/^[a-zA-Z0-9]+$/', $db_data['channel_key']))
        {
            $errors[] = '渠道标识符只能是英文数字字母组合！';
        }
        if (empty($errors))
        {
            $query_params = [
                'where' => "`name` = '{$db_data['name']}' OR `channel_key` = '{$db_data['channel_key']}'",
            ];
            $channel_list = table('app_channel', 'platform')->gets($query_params);
            $name_exists = false;
            $key_exists = false;
            foreach ($channel_list as $channel)
            {
                if ($channel['name'] === $db_data['name'])
                {
                    $name_exists = true;
                }
                if ($channel['channel_key'] === $db_data['channel_key'])
                {
                    $key_exists = true;
                }
            }
            if ($name_exists && ($data['channel']['name'] !== $db_data['name']))
            {
                $errors[] = '已存在同名的渠道名称！';
            }
            if ($key_exists && ($data['channel']['channel_key'] !== $db_data['channel_key']))
            {
                $errors[] = '已存在同名的渠道标识符！';
            }
        }
        return $db_data;
    }

    function editChannelReleaseLinkCheck($input, &$errors)
    {
        $output['channel_id'] = trim(array_get($input, 'app_channel'));
        if ($output['channel_id'] !== '')
        {
            $query_params = [
                'where' => "id = '{$output['channel_id']}'",
            ];
            $count = table('app_channel', 'platform')->count($query_params);
            if (!$count)
            {
                $errors[] = 'APP渠道不存在！';
            }
        }
        else
        {
            $errors[] = 'APP渠道不能为空！';
        }
        $output['release_link'] = trim(array_get($input, 'release_link'));
        if ($output['release_link'] === '')
        {
            $errors[] = '下载地址不能为空！';
        }
        if (!is_valid_url($output['release_link']))
        {
            $errors[] = '请填写正确的下载地址！';
        }
        return $output;
    }
}