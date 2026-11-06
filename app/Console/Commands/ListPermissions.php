<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class ListPermissions extends Command
{
    protected $signature = 'permissions:list-friendly';

    protected $description = 'List permissions grouped by resource in a user-friendly format';

    public function handle()
    {
        $perms = Permission::where('guard_name', 'web')->get()->pluck('name')->toArray();

        $groups = [];
        foreach ($perms as $p) {
            // Accept current and legacy permission naming conventions.
            if (preg_match('/^(list|read|write)_(.+)$/', $p, $m)) {
                $groups[$m[2]][$m[1]] = $p;

                continue;
            }
            if (preg_match('/^view_any_(.+)$/', $p, $m)) {
                $groups[$m[1]]['list_legacy'] = $p;

                continue;
            }
            if (preg_match('/^view_(.+)$/', $p, $m)) {
                $groups[$m[1]]['read_legacy'] = $p;

                continue;
            }
            if (preg_match('/^(create|update|delete)_(.+)$/', $p, $m)) {
                $groups[$m[2]]['write_legacy'][] = $p;

                continue;
            }
            $groups['other'][$p] = $p;
        }

        foreach ($groups as $resource => $data) {
            $this->line('Resource: '.$resource);
            $this->line('  List:    '.($data['list'] ?? $data['list_legacy'] ?? '-'));
            $this->line('  Read:    '.($data['read'] ?? $data['read_legacy'] ?? '-'));
            $this->line('  Write:   '.($data['write'] ?? implode(', ', $data['write_legacy'] ?? []) ?: ($data['write_legacy'] ?? '-')));
            $this->line('');
        }

        return 0;
    }
}
