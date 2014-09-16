<?php namespace Sugarcrm\Portals\Controllers\Admin;

use Illuminate\Support\MessageBag,
    Sugarcrm\Portals\Services\Validators\FileValidator,
    Sugarcrm\Portals\Controllers\BaseController,
    App,
    View,
    Config,
    Input,
    File,
    Str,
    Redirect,
    Response;

class FilesController extends BaseController
{
    protected $auth;
    protected $file;
    protected $validator;

    public function __construct(\Sugarcrm\Portals\Repo\File $file, \Cartalyst\Sentry\Sentry $auth)
    {
        $app               = app();
        $this->auth        = $auth;
        $this->file        = $file;
        $this->validator   = new FileValidator($app['validator'], new MessageBag);
        //$this->filemanager = App::make('flysystem');
        parent::__construct();
    }

    protected function getUserGroups()
    {
        $userGroups = array();
        $addGroups  = $this->auth->getGroupProvider()->findAll();
        foreach ($addGroups as $item) {
            $userGroups[$item->id] = $item->name;
        }
        return $userGroups;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $files = $this->file->paginate(15);

        $this->layout->content = View::make(
            Config::get('portals::files.admin.index', 'portals::admin.files.index'),
            compact('files')
        );
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $userGroups = $this->getUserGroups();

        $this->layout->content = View::make(
            Config::get('portals::files.admin.create', 'portals::admin.files.create'),
            compact('userGroups')
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
        $input      = Input::only('title', 'description', 'keywords', 'permissions');
        $input_file = Input::file('file');

        if (!$this->validator->with(Input::all())->passes()) {
            return Redirect::back()->withInput()->withErrors($this->validator->getErrors());
        }

        $this->file->fmWriteStream($input_file);

        $input['filename']  = $input_file->getClientOriginalName();
        $input['extension'] = $input_file->getClientOriginalExtension();
        $input['type']      = $input_file->getMimeType();
        $input['size']      = $input_file->getSize();
        //$input['user_id']   = $this->auth->getUser()->getId(); // TODO: does not see getId()

        $file = $this->file->create($input);

        return Redirect::route('admin.files.edit', array($file->id));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return Response
     */
    public function edit($id)
    {
        $file = $this->file->find($id);

        $userGroups = $this->getUserGroups();

        $this->layout->content = View::make(
            Config::get('portals::files.admin.edit', 'portals::admin.files.edit'),
            compact('file', 'userGroups')
        );
    }

    /**
     * Download file from Storage
     *
     * @param  int $id
     * @return Response
     */
    public function download($id)
    {
        $file = $this->file->find($id);

        if (is_null($file)) {
            return Redirect::route('admin.files.view')->with('error', 'File not found.');
        }

        if (!$this->filemanager->has($file->filename)) {
            return Redirect::route('admin.files.view')->with('error', $file->filename . ' not found on the hard drive');
        }

        $tmpfname = $this->file->fmReadStream($file);

        // save download
        $file->increment('downloads');

        return Response::download($tmpfname, $file->filename);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int $id
     * @return Response
     */
    public function update($id)
    {
        $input = Input::only('title', 'description', 'keywords', 'permissions');
        if (!$this->validator->with($input)->passes()) {
            return Redirect::back()->withInput()->withErrors($this->validator->getErrors());
        }
        $file = $this->file->find($id);
        $file->update($input);

        return Redirect::route('admin.files.edit', array($id))->with(
            'success',
            "File information '{$input['title']}' has been saved"
        );
    }

}
