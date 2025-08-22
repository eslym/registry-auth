<?php

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $grammar = DB::connection()->getSchemaGrammar();
        $userClass = DB::escape(User::class);
        $groupClass = DB::escape(Group::class);
        $query = DB::table('access_controls')
            ->select([
                "ac.id AS id",
                DB::raw("$userClass AS owner_type"),
                DB::raw("COALESCE(g.user_id, ac.owner_id) AS owner_id"),
                DB::raw("g.group_id AS group_id"),
                "ac.rule AS rule",
                "ac.access_level AS access_level",
                "ac.sort_order AS sort_order",
                DB::raw("g.sort_order AS group_order"),
                "ac.created_at AS created_at",
                "ac.updated_at AS updated_at",
            ])
            ->from('access_controls AS ac')
            ->leftJoin('user_group AS g', fn($join) => $join
                ->on('ac.owner_id', '=', 'g.group_id')
                ->whereRaw("ac.owner_type = $groupClass")
            )
            ->whereRaw("ac.owner_type IN ($userClass, $groupClass)")
            ->orderBy('group_order')
            ->orderBy('sort_order');
        $view = $grammar->wrapTable('user_acls');
        $sql = $query->toSql();
        DB::statement("DROP VIEW IF EXISTS $view");
        DB::statement("CREATE VIEW $view AS $sql;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $grammar = DB::connection()->getSchemaGrammar();
        $view = $grammar->wrapTable('user_acls');
        DB::statement("DROP VIEW IF EXISTS $view");
        $migration = require(__DIR__ . '/2025_08_20_074827_create_users_acl_view.php');
        (new $migration())->up();
    }
};
