<?php

namespace Webbycrown\BlogBagisto\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Webbycrown\BlogBagisto\Datagrids\TagDataGrid;
use Webbycrown\BlogBagisto\Repositories\BlogTagRepository;
use Webbycrown\BlogBagisto\Http\Requests\BlogTagRequest;
use Webkul\Admin\Http\Requests\MassDestroyRequest;

class TagController extends Controller
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
    public function __construct(protected BlogTagRepository $blogTagRepository)
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
            return app(TagDataGrid::class)->toJson();
        }

        return view($this->_config['view']);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $locale = core()->getRequestedLocaleCode();

        return view($this->_config['view'])->with('locale', $locale);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(BlogTagRequest $blogTagRequest)
    {
        /*$this->validate(request(), [
            'slug'                  => 'slug', 'unique',
            'name'                  => 'required',
            'description'           => 'required',
        ]);*/

        $data = request()->all();

        if (is_array($data['locale'])) {
            $data['locale'] = implode(',', $data['locale']);
        }

        $result = $this->blogTagRepository->save($data);

        if ($result) {
            session()->flash('success', trans('blog::app.tag.create-success'));
        } else {
            session()->flash('success', trans('blog::app.tag.created-fault'));
        }

        return redirect()->route($this->_config['redirect']);
    }

    /**
     * Show the form for editing the specified resource. 
     */
    public function edit($id)
    {
        $tag = $this->blogTagRepository->findOrFail($id);

        return view($this->_config['view'], compact('tag'));
    }

    /**
     * Update the specified resource in storage. 
     */
    public function update(BlogTagRequest $blogTagRequest, $id)
    {
        /*$this->validate(request(), [
            'slug'                  => 'slug', 'unique',
            'name'                  => 'required',
            'description'           => 'required',
        ]);*/

        $data = request()->all();

        if (is_array($data['locale'])) {
            $data['locale'] = implode(',', $data['locale']);
        }

        $result = $this->blogTagRepository->updateItem($data, $id);

        if ($result) {
            session()->flash('success', trans('blog::app.tag.update-success'));
        } else {
            session()->flash('error', trans('blog::app.tag.updated-fault'));
        }

        return redirect()->route($this->_config['redirect']);
    }

    /**
     * Remove the specified resource from storage. 
     */
    public function destroy($id)
    {
        $this->blogTagRepository->findOrFail($id);

        try {
            $this->blogTagRepository->delete($id);

            return response()->json(['message' => trans('blog::app.tag.delete-success')]);
        } catch (\Exception $e) {
            report($e);
        }

        return response()->json(['message' => trans('blog::app.tag.delete-failed')], 500);
    }

    /**
     * Remove the specified resources from database.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $indices = $massDestroyRequest->input('indices');

        foreach ($indices as $index) {
            Event::dispatch('catalog.blog.tag.delete.before', $index);

            $this->blogTagRepository->delete($index);

            Event::dispatch('catalog.blog.tag.delete.after', $index);
        }

        return new JsonResponse([
            'message' => trans('blog::app.tag.datagrid.mass-delete-success'),
        ]);
    }
}
