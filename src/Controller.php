<?php namespace Barryvdh\TranslationManager;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Barryvdh\TranslationManager\Models\Translation;
use Illuminate\Support\Collection;

class Controller extends BaseController
{
    /** @var \Barryvdh\TranslationManager\Manager  */
    protected $manager;
    protected $columns;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
        $this->columns = config('translation-manager.columns');
    }

    public function getIndex($group = null)
    {
        $locales = $this->loadLocales();
        $groups = Translation::groupBy($this->columns['group'] ?? 'group');
        $excludedGroups = $this->manager->getConfig('exclude_groups');
        if($excludedGroups){
            $groups->whereNotIn($this->columns['group'] ?? 'group', $excludedGroups);
        }

        $groups = $groups->select($this->columns['group'] ?? 'group')->get()->pluck($this->columns['group'] ?? 'group', $this->columns['group'] ?? 'group');
        if ($groups instanceof Collection) {
            $groups = $groups->all();
        }

        $groups = [''=>'Choose a group'] + $groups;
        $numChanged = Translation::where($this->columns['group'] ?? 'group', $group)->where($this->columns['status'] ?? 'status', Translation::STATUS_CHANGED)->count();


        $allTranslations = Translation::where($this->columns['group'] ?? 'group', $group)->orderBy($this->columns['key'] ?? 'key', 'asc')->get();
        $numTranslations = count($allTranslations);
        $translations = [];
        foreach($allTranslations as $translation){
            $translations[$translation->{$this->columns['key'] ?? 'key'}][$translation->{$this->columns['locale'] ?? 'locale'}] = $translation;
        }

         return view('translation-manager::index')
            ->with('translations', $translations)
            ->with('locales', $locales)
            ->with('groups', $groups)
            ->with('group', $group)
            ->with('numTranslations', $numTranslations)
            ->with('numChanged', $numChanged)
            ->with('editUrl', action('\Barryvdh\TranslationManager\Controller@postEdit', [$group]))
            ->with('deleteEnabled', $this->manager->getConfig('delete_enabled'));
    }

    public function getView($group = null)
    {
        return $this->getIndex($group);
    }

    protected function loadLocales()
    {
        //Set the default locale as the first one.
        $locales = Translation::groupBy($this->columns['locale'] ?? 'locale')
            ->select($this->columns['locale'] ?? 'locale')
            ->get()
            ->pluck($this->columns['locale'] ?? 'locale');

        if ($locales instanceof Collection) {
            $locales = $locales->all();
        }
        $locales = array_merge([config('app.locale')], $locales);
        return array_unique($locales);
    }

    public function postAdd($group = null)
    {
        $keys = explode("\n", request()->get('keys'));

        foreach($keys as $key){
            $key = trim($key);
            if($group && $key){
                $this->manager->missingKey('*', $group, $key);
            }
        }
        return redirect()->back();
    }

    public function postEdit($group = null)
    {
        if(!in_array($group, $this->manager->getConfig('exclude_groups'))) {
            $name = request()->get('name');
            $value = request()->get('value');

            list($locale, $key) = explode('|', $name, 2);
            $translation = Translation::firstOrNew([
                'trans_locale' => $locale,
                'trans_group' => $group,
                'trans_key' => $key,
            ]);
            $translation->trans_value = (string) $value ?: null;
            $translation->trans_status = Translation::STATUS_CHANGED;
            $translation->save();
            return array($this->columns['status'] ?? 'status' => 'ok');
        }
    }

    public function postDelete($group = null, $key)
    {
        if(!in_array($group, $this->manager->getConfig('exclude_groups')) && $this->manager->getConfig('delete_enabled')) {
            Translation::where($this->columns['group'] ?? 'group', $group)->where($this->columns['key'] ?? 'key', $key)->delete();
            return ['status' => 'ok'];
        }
    }

    public function postImport(Request $request)
    {
        $replace = $request->get('replace', false);
        $counter = $this->manager->importTranslations($replace);

        return ['status' => 'ok', 'counter' => $counter];
    }

    public function postFind()
    {
        $numFound = $this->manager->findTranslations();

        return ['status' => 'ok', 'counter' => (int) $numFound];
    }

    public function postPublish($group = null)
    {
         $json = false;

        if($group === '_json'){
            $json = true;
        }

        $this->manager->exportTranslations($group, $json);

        return ['status' => 'ok'];
    }
}
