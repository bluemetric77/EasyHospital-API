<?php

namespace App\Http\Controllers\Config;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Config\Users;
use App\Models\Config\Objects;
use App\Models\Config\UserObjects;
use App\Models\Config\Reports;
use App\Models\Config\USessions;
use Helpers;

class HomeController extends Controller
{
    public function PageEnvironment(Request $request){
        $group_id = isset($request->group_id) ? $request->group_id : -1;
        $session=Helpers::Session();
        if (!($session)) {
            return response()->error('',301,"Page access denied");
        }
        $user_sysid = $session->user_sysid;
        $url        = $request->id;
        $item=Helpers::PageEnviroment($url);
        $access=array();

        $action=json_decode($item->security,true);
        foreach ($action as $act) {
            $access[$act['action']]=true;
        }

        if ($session->user_level=='USER') {
            foreach ($action as $act) {
                $access[$act['action']]=false;
            }
            $allowed=UserObjects::selectRaw("security")
            ->where('user_sysid',$user_sysid)
            ->where('object_id',$item->sysid)
            ->first();

            $action=json_decode($allowed->security,true);
            foreach ($action as $act) {
                $access[$act]=true;
            }
        }

        $item->{'access'}=$access;

        return response()->success('Success',$item);

    }

    public function ObjectItem(Request $request){
        $session=Helpers::Session();
        if (!($session)) {
            return response()->error('',301,"Acceess denied");
        }

        $sysid=$session->user_sysid;

        if ($session->user_level=='USER'){
            $item = Objects::selectRaw("sysid,sort_number,parent_sysid,object_level,title,icons,url_link,is_parent,CONCAT('g_',parent_sysid) as parent_sysid_text")
            ->where('is_active',true)
            ->whereIn('sysid',function ($query) use ($sysid){
                $query->select('object_sysid')
                    ->from('o_users_access')
                    ->where('user_sysid', $sysid)
                    ->distinct()
                    ->get();
            });
            $item=$item
                ->orwhere('is_parent',true)
                ->orwhere('sort_number',9001)
                ->distinct()
                ->orderBy('sort_number')
                ->get();

        } else {
            $item = Objects::selectRaw("sysid,sort_number,parent_sysid,object_level,title,icons,url_link,is_parent,CONCAT('g_',parent_sysid) as parent_sysid_text")
            ->where('is_active',true)
            ->distinct()
            ->orderBy('sort_number')
            ->get();
        }
        return response()->success('Success',$item);
    }

    public function ObjectReport(Request $request){
        $session=Helpers::Session();
        if (!($session)) {
            return response()->error('',301,"Acceess denied");
        }
        $sysid=$session->user_sysid;
        if ($data->user_level=='USER'){
            $item = DB::table('o_reports')
            ->selectRaw('id,level,sort_number,group_id,title,image,url_link,icon,notes,colidx,is_header,dialog_model')
            ->where('group_id','<>',-1)
            ->where(function($query) use ($sysid) {
                $query->whereIn('id',function ($query) use ($sysid){
                    $query->select('report_id')
                        ->from('o_user_report')
                        ->where('sysid', $sysid)
                        ->where('is_allow', '1')
                        ->distinct()
                        ->get();
                })
                ->orwhere('is_header','1');
            });
            $item=$item
            ->distinct()
            ->orderBy('sort_number')
            ->get();
        } else {
            $item = DB::table('o_reports')
            ->selectRaw('id,level,sort_number,group_id,title,image,url_link,icon,notes,colidx,is_header,dialog_model')
            ->where('group_id','<>',-1)
            ->distinct()
            ->orderBy('sort_number')
            ->get();
        }
        return response()->success('Success',$item);
    }

    public function setReport(Request $request){
        $sysid=isset($request->sysid) ? $request->sysid :'-1';
        $item = Reports::from('o_object_reports as a')
                ->selectRaw('a.id,a.level,a.sort_number,a.group_id,a.title,a.colidx,a.is_header,
                IFNULL(b.is_allow,a.is_selected) as is_selected')
                ->leftjoin('o_users_report as b', function($join) use ($sysid)
                {
                    $join->on('a.id', '=', 'b.report_id');
                    $join->on('b.sysid', '=', DB::raw($sysid));
                })
                ->where('a.group_id','<>',-1)
                ->distinct()
                ->orderBy('a.sort_number')
                ->get();
        return response()->success('Success',$item);
    }
}
