<?php

namespace Zhiyi\Plus\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Zhiyi\Plus\Support\Configuration;
use Zhiyi\Plus\Models\VerificationCode;
use Illuminate\Contracts\Config\Repository;
use Zhiyi\Plus\Http\Controllers\Controller;
use Illuminate\Contracts\Routing\ResponseFactory;

class SmsController extends Controller
{
    /**
     * Show SMS logs.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Contracts\Routing\ResponseFactory $response
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function show(Request $request, ResponseFactory $response, VerificationCode $model)
    {
        $state = $request->query('state');
        $phone = $request->query('phone');
        $limit = $request->query('limit', 20);

        $data = $model->withTrashed()
            ->when(boolval($state), function ($query) use ($state) {
                return $query->where('state', $state);
            })
            ->when(boolval($phone), function ($query) use ($phone) {
                return $query->where('account', 'like', sprintf('%%%s%%', $phone));
            })
            ->orderBy('id', 'desc')
            ->simplePaginate($limit);

        return $response->json($data, 200);
    }

    /**
     * 获取短信所有配置网关.
     *
     * @param Repository $config
     * @return ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function showGateway(Repository $config)
    {
        $data = [];
        $data['gateways'] = array_keys($config->get('sms.gateways'));
        $data['allowed_gateways'] = $config->get('sms.default.allowed_gateways');
        $data['default_gateways'] = $config->get('sms.default.gateways');

        return response($data, 200);
    }

    /**
     * 更新允许的网关.
     *
     * @param Request $request
     * @param Repository $config
     * @param Configuration $store
     * @return ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function updateGateway(Request $request, Repository $config, Configuration $store)
    {
        $gateways = $request->input('gateways');
        $type = $request->input('type');

        if (! is_array($gateways) || ! $type) {
            return response(['message' => ['数据格式错误或类型参数错误']], 422);
        }

        $config = $store->getConfiguration();

        $key = ($type === 'sms') ? 'gateways' : 'allowed_gateways';

        $config->set(sprintf('sms.default.%s', $key), $gateways);

        $store->save($config);

        return response(['message' => ['更新成功']], 201);
    }

    /**
     * Get SMS driver configuration information.
     *
     * @param \Illuminate\Contracts\Config\Repository $config
     * @param \Illuminate\Contracts\Routing\ResponseFactory $response
     * @param string $driver
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function showOption(Repository $config, ResponseFactory $response, string $driver)
    {
        if (! in_array($driver, array_keys($config->get('sms.gateways')))) {
            return $response->json(['message' => ['当前驱动不存在于系统中']], 422);
        }

        $data = $config->get(sprintf('sms.gateways.%s', $driver), []);

        if ($driver === 'yunpian') {
            $data['content'] = $config->get(sprintf('sms.channels.code.%s.content', $driver));
        } else {
            $data['verify_template_id'] = $config->get(sprintf('sms.channels.code.%s.template', $driver));
        }

        return $response->json($data, 200);
    }

    /**
     * Update Ali SMS configuration information.
     *
     * @param Repository $config
     * @param Configuration $store
     * @param Request $request
     * @param ResponseFactory $response
     * @param string $driver
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function updateAlidayuOption(Repository $config, Configuration $store, Request $request, ResponseFactory $response)
    {
        $config = $store->getConfiguration();
        $config->set(
            'sms.gateways.alidayu',
            $request->only(['app_key', 'app_secret', 'sign_name'])
        );
        $config->set(
            'sms.channels.code.alidayu.template',
            $request->input('verify_template_id')
        );
        $store->save($config);

        return $response->json(['message' => ['更新成功']], 201);
    }

    /**
     * Update Aliyun SMS configuration information.
     *
     * @param Repository $config
     * @param Configuration $store
     * @param Request $request
     * @param ResponseFactory $response
     * @param string $driver
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function updateAliyunOption(Repository $config, Configuration $store, Request $request)
    {
        $config = $store->getConfiguration();
        $config->set(
            'sms.gateways.aliyun',
            $request->only(['access_key_id', 'access_key_secret', 'sign_name'])
        );
        $config->set(
            'sms.channels.code.aliyun.template',
            $request->input('verify_template_id')
        );
        $store->save($config);

        return response()->json(['message' => ['更新成功']], 201);
    }

    /**
     * Update Yunpian SMS configuration information.
     *
     * @param Repository $config
     * @param Configuration $store
     * @param Request $request
     * @param ResponseFactory $response
     * @param string $driver
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function updateYunpianOption(Repository $config, Configuration $store, Request $request)
    {
        $config = $store->getConfiguration();

        $config->set(
            'sms.gateways.yunpian',
            $request->only(['api_key'])
        );

        if (strpos($request->input('content'), ':code') === false) {
            return response()->json(['message' => [':code变量不存在']], 422);
        }

        $config->set(
            'sms.channels.code.yunpian.content',
            $request->input('content')
        );

        $store->save($config);

        return response()->json(['message' => ['更新成功']], 201);
    }
}
