<?php

namespace App;

use App\Http\Controllers\SeoController;
use App\Interfaces\ISeoAble;
use App\Menu\MenuItem;
use App\Menu\MenuTransformer;
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;
use Cviebrock\EloquentTaggable\Taggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Blog extends Model implements MenuItem, ISeoAble
{

    use SoftDeletes;
    use MenuTransformer;
    use Taggable;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'id_category',
        'id_user',
        'title',
        'content',
        "image",
        "id_language",
        "id_default",
        "slug"
    ];


    public function seoUrl()
    {
        return "/{$this->category()->first()->slug}/{$this->slug()}";
    }

    protected static function boot()
    {
        parent::boot();
        self::saved(function ($model) {
            (new SeoController())->updateModel($model);
        });
        self::created(function ($model) {
            (new SeoController())->updateModel($model);
        });
    }

    protected $table = 'blog_posts';

    protected $guarded = ['id'];

    public static function allDefaultTranslates()
    {
        return DB::table("blog_posts")->whereNull("id_default")->whereNull("deleted_at")->get();
    }

    public function comments()
    {
        return $this->hasMany(BlogComment::class);
    }

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'id_category');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function getBlogCategoryAttribute()
    {
        return $this->category->pluck('id');
    }

    public function images()
    {
        return $this->belongsToMany(File::class, "blog_images_relations", "id_blog", "id_file", "id", "id");
    }

    public function image()
    {
        return $this->hasOne(File::class, "id", "image");
    }

    public function translates()
    {
        return $this->hasMany(Blog::class, "id_default", "id");
    }

    public function name()
    {
        return $this->title;
    }

    public function slug()
    {
        return $this->slug;
    }

    public function fullUrl()
    {
        return "/blog/{$this->slug()}";
    }

    public function itemLabel()
    {
        return 'Blog';
    }

    public function id()
    {
        return $this->id;
    }

    public function createGalleryDataContent()
    {
        $data = array();
        foreach ($this->images()->get()->toArray() as $one) {
            $href = '/storage/files/' . $one["name"];
            $template = '<div class="image-wrapper"><a data-fancybox="gallery" href="' . $href . '"><img src="' . $href . '" alt="Gallery image"></a></div>';
            array_push($data, $template);
        };
        return $data;
    }

    public function getSeoData()
    {
        return ["id" => $this->id, "name" => $this->title];
    }

    public function seo()
    {
        return $this->hasOne(SeoData::class, "id", "id_relation")->where("relation_type", "BLOG");
    }

}
