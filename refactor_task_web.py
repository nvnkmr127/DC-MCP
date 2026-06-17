import re

with open('app/Modules/ProjectManagement/Http/Controllers/TaskWebController.php', 'r') as f:
    content = f.read()

# Add imports
imports = """use App\\Modules\\ProjectManagement\\Http\\Requests\\StoreTaskRequest;
use App\\Modules\\ProjectManagement\\Http\\Requests\\UpdateTaskRequest;
use App\\Shared\\Enums\\TaskStatus;
use App\\Shared\\Enums\\TaskType;
use App\\Shared\\Enums\\TaskPriority;
use App\\Shared\\Enums\\RoleType;"""

content = re.sub(r'use Illuminate\\Validation\\Rule;\n', r'use Illuminate\\Validation\\Rule;\n' + imports + '\n', content)

# 1. replace index
content = re.sub(
    r'public function index\(Request \$request\).*?\$query = Task::with\(\[\'project:id,name\', \'assignee:id,name\'\]\)',
    r"""public function index(Request $request)
    {
        if (!$request->user()->hasPermission('view', 'task')) {
            abort(403);
        }

        $query = Task::with(['project:id,name', 'assignee:id,name'])""",
    content,
    flags=re.DOTALL
)

# Fix status enum casting in index
content = re.sub(
    r"'status'          => \$t->status,",
    r"'status'          => is_object($t->status) ? $t->status->value : $t->status,",
    content
)
content = re.sub(
    r"'priority'        => \$t->priority,",
    r"'priority'        => is_object($t->priority) ? $t->priority->value : $t->priority,",
    content
)

# 2. create
content = re.sub(
    r'public function create\(Request \$request\).*?\$projects = Project::select\(\'id\', \'name\'\)',
    r"""public function create(Request $request)
    {
        if (!$request->user()->hasPermission('create', 'task')) {
            abort(403);
        }

        $projects = Project::select('id', 'name')""",
    content,
    flags=re.DOTALL
)

# 3. store
store_orig = r"""    public function store\(Request \$request\)
    \{
        \$data = \$request->validate\(\[.*?\]\);

        \$task = Task::create\(\[
            \.\.\.\$data,
            'organization_id' => \$request->user\(\)->organization_id,
            'created_by'      => \$request->user\(\)->id,
        \]\);

        return redirect\(\)->route\('web\.tasks\.show', \$task\)->with\('success', 'Task created\.'\);
    \}"""

store_new = """    public function store(StoreTaskRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $task = Task::create($data);

        return redirect()->route('web.tasks.show', $task)->with('success', 'Task created.');
    }"""
content = re.sub(store_orig, store_new, content, flags=re.DOTALL)

# 4. show
content = re.sub(
    r'public function show\(Task \$task\)\n    \{',
    r"""public function show(Request $request, Task $task)
    {
        if (!$request->user()->hasPermission('view', 'task')) {
            abort(403);
        }""",
    content
)
content = re.sub(
    r"'status'     => \$t->status,",
    r"'status'     => is_object($t->status) ? $t->status->value : $t->status,",
    content
)

# 5. edit
content = re.sub(
    r'public function edit\(Task \$task\)\n    \{',
    r"""public function edit(Request $request, Task $task)
    {
        if (!$request->user()->hasPermission('update', 'task')) {
            abort(403);
        }""",
    content
)

# 6. update
update_orig = r"""    public function update\(Request \$request, Task \$task\)
    \{
        \$data = \$request->validate\(\[.*?\]\);"""

update_new = """    public function update(UpdateTaskRequest $request, Task $task)
    {
        $data = $request->validated();"""
content = re.sub(update_orig, update_new, content, flags=re.DOTALL)

# 7. destroy
content = re.sub(
    r'public function destroy\(Task \$task\)\n    \{',
    r"""public function destroy(Request $request, Task $task)
    {
        if (!$request->user()->hasPermission('delete', 'task')) {
            abort(403);
        }""",
    content
)

# 8. remove $this->authorizeOrg from other methods
content = re.sub(r'\s*\$this->authorizeOrg\([^)]+\);\n', '\n', content)

with open('app/Modules/ProjectManagement/Http/Controllers/TaskWebController.php', 'w') as f:
    f.write(content)
