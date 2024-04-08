<?php

namespace Webbycrown\BlogBagisto\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Webbycrown\BlogBagisto\Datagrids\BlogDataGrid;
use Webbycrown\BlogBagisto\Models\Category;
use Webbycrown\BlogBagisto\Models\Tag;
use Webbycrown\BlogBagisto\Models\Blog;
use Webbycrown\BlogBagisto\Repositories\BlogRepository;
use Webkul\User\Models\Admin;
use Webbycrown\BlogBagisto\Http\Requests\BlogRequest;
use Webkul\Admin\Http\Requests\MassDestroyRequest;

class BlogController extends Controller
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * Contains route related configuration
     *
     * @var array
     */
    protected $_config;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected BlogRepository $blogRepository)
    {
        $this->middleware('admin');

        $this->_config = request('_config');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if (request()->ajax()) {
            return app(BlogDataGrid::class)->toJson();
        }

        return view($this->_config['view']);
    }

    public function gteBlogs()
    {
        return 'This is blog API';
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $locale = core()->getRequestedLocaleCode();

        $categories = Category::all();
        
        $additional_categories = Category::where('parent_id', 0 )->where('status', 1)->get();
        
        $tags = Tag::all();

        $users = Admin::all();

        return view($this->_config['view'], compact('categories', 'tags', 'users', 'additional_categories'))->with('locale', $locale);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(BlogRequest $blogRequest)
    {
        $data = request()->all();

        if (array_key_exists('locale', $data) &&  is_array($data['locale'])) {
            $data['locale'] = implode(',', $data['locale']);
        }

        if (array_key_exists('tags', $data) &&  is_array($data['tags'])) {
            $data['tags'] = implode(',', $data['tags']);
        }

        if (array_key_exists('categories', $data) && is_array($data['categories'])) {
            $data['categories'] = implode(',', $data['categories']);
        }

        $data['author'] = '';
        if (is_array($data) && array_key_exists('author_id', $data) && isset($data['author_id']) && (int)$data['author_id'] > 0) {
            $author_data = Admin::where('id', $data['author_id'])->first();
            $data['author'] = ($author_data && !empty($author_data)) ? $author_data->name : '';
        }

        $result = $this->blogRepository->save($data);

        if ($result) {
            session()->flash('success', trans('blog::app.blog.create-success'));
        } else {
            session()->flash('success', trans('blog::app.blog.created-fault'));
        }

        return redirect()->route($this->_config['redirect']);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $loggedIn_user = auth()->guard('admin')->user()->toarray();
        $user_id = (array_key_exists('id', $loggedIn_user)) ? $loggedIn_user['id'] : 0;
        $role = (array_key_exists('role', $loggedIn_user)) ? (array_key_exists('name', $loggedIn_user['role']) ? $loggedIn_user['role']['name'] : 'Administrator') : 'Administrator';

        $blog = $this->blogRepository->findOrFail($id);

        if ($blog && $user_id != $blog->author_id && $role != 'Administrator') {
            return redirect()->route('admin.blog.index');
        }

        $categories = Category::all();

        $additional_categories = Category::where('parent_id', 0)->where('status', 1)->get();

        $tags = Tag::all();

        $users = Admin::all();

        return view($this->_config['view'], compact('blog', 'categories', 'tags', 'users', 'additional_categories'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(BlogRequest $blogRequest, $id)
    {

        $data = request()->all();

        if (array_key_exists('locale', $data) && is_array($data['locale'])) {
            $data['locale'] = implode(',', $data['locale']);
        }

        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            $data['tags'] = implode(',', $data['tags']);
        }

        if (array_key_exists('categories', $data) && is_array($data['categories'])) {
            $data['categories'] = implode(',', $data['categories']);
        }

        $data['author'] = '';
        if (is_array($data) && array_key_exists('author_id', $data) && isset($data['author_id']) && (int)$data['author_id'] > 0) {
            $author_data = Admin::where('id', $data['author_id'])->first();
            $data['author'] = ($author_data && !empty($author_data)) ? $author_data->name : '';
        }

        $result = $this->blogRepository->updateItem($data, $id);

        if ($result) {
            session()->flash('success', trans('blog::app.blog.update-success'));
        } else {
            session()->flash('error', trans('blog::app.blog.updated-fault'));
        }

        return redirect()->route($this->_config['redirect']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->blogRepository->findOrFail($id);

        try {
            $this->blogRepository->delete($id);

            return response()->json(['message' => trans('blog::app.blog.delete-success')]);
        } catch (\Exception $e) {
            report($e);
        }

        return response()->json(['message' => trans('blog::app.blog.delete-failure')], 500);
    }

    /**
     * Remove the specified resources from database.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $indices = $massDestroyRequest->input('indices');

        foreach ($indices as $index) {
            Event::dispatch('catalog.blog.delete.before', $index);

            $this->blogRepository->delete($index);

            Event::dispatch('catalog.blog.delete.after', $index);
        }

        return new JsonResponse([
            'message' => trans('blog::app.blog.datagrid.mass-delete-success'),
        ]);
    }
}
