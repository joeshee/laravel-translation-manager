<?php namespace Barryvdh\TranslationManager\Models;

use App\Application;
use Illuminate\Database\Eloquent\Model;
use DB;

/**
 * Translation model
 *
 * @property integer $id
 * @property integer $status
 * @property string  $locale
 * @property string  $group
 * @property string  $key
 * @property string  $value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Translation extends Model{

    const STATUS_SAVED = 0;
    const STATUS_CHANGED = 1;
    const CREATED_AT = 'trans_created_at';
    const UPDATED_AT = 'trans_updated_at';

    protected $table = 'ltm_translations';
    protected $primaryKey = 'trans_identity';
    protected $columns;
//    protected $guarded = array('id', 'created_at', 'updated_at');

    public function __construct()
    {
        parent::__construct();
        if(! empty( $table = config('translation-manager.table_name') )) {
            $this->table = $table;
        }
        $this->columns = config('translation-manager.columns');
    }

    public function scopeOfTranslatedGroup($query, $group)
    {
        return $query->where($this->columns['group'] ?? 'group', $group)->whereNotNull('trans_value');
    }

    public function scopeOrderByGroupKeys($query, $ordered) {
        if ($ordered) {
            $query->orderBy($this->columns['group'] ?? 'group')->orderBy('trans_key');
        }

        return $query;
    }

    public function scopeSelectDistinctGroup($query)
    {
        $select = '';
        $groupColumn = $this->columns['group'] ?? 'group';

        switch (DB::getDriverName()){
            case 'mysql':
                $select = 'DISTINCT `'.$groupColumn.'`';
                break;
            default:
                $select = 'DISTINCT "'.$groupColumn.'"';
                break;
        }

        return $query->select(DB::raw($select));
    }

}
