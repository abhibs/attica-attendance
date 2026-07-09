<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class RoleController extends Controller
{
    public function allPermission(){
        $permissions = Permission::all();
        return view('admin.permission.all_permission',compact('permissions'));
    }


    public function addPermission(){
        return view('admin.permission.add_permission');
    }

    public function storePermission(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'group_name' => ['required', 'string', 'max:255'],
        ]);

        Permission::create([
            'name' => $validated['name'],
            'group_name' => $validated['group_name'],
            'guard_name' => 'admin',
        ]);

        return redirect()->route('admin-all-permission')->with('flash_success', 'Permission added successfully.');
    }

    public function editPermission($id)
    {
        $permission = Permission::findOrFail($id);

        return view('admin.permission.edit_permission', compact('permission'));
    }

    public function updatePermission(Request $request, $id = null)
    {
        $permissionId = $id ?: $request->input('id');

        if (! $permissionId) {
            return redirect()->route('admin-all-permission')->with('flash_warning', 'Permission not found.');
        }

        $request->merge(['id' => $permissionId]);

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:permissions,id'],
            'name' => ['required', 'string', 'max:255'],
            'group_name' => ['required', 'string', 'max:255'],
        ]);

        Permission::findOrFail($validated['id'])->update([
            'name' => $validated['name'],
            'group_name' => $validated['group_name'],
            'guard_name' => 'admin',
        ]);

        return redirect()->route('admin-all-permission')->with('flash_success', 'Permission updated successfully.');
    }

    public function deletePermission($id)
    {
        Permission::findOrFail($id)->delete();

        return redirect()->back()->with('flash_success', 'Permission deleted successfully.');
    }
}
