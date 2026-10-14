<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NetFusion Framework — Phase 35 Test Suite
|--------------------------------------------------------------------------
|
| File      : scratch/test_phase35.php
| Module    : Forms
| Purpose   : Comprehensive tests for Dynamic Form Engine, Fields, Schema Validator,
|             and HasCustomFields Trait.
|
| Author    : Md. Atiquzzaman Ashish
| Company   : DotNetBD
| Website   : www.dotnetbd.com
| Copyright : © DotNetBD. All rights reserved.
| License   : MIT
| Version   : 1.0.0
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../vendor/autoload.php';

use NetFusion\Core\Application;
use NetFusion\Core\Config\Config;
use NetFusion\Core\Database\DatabaseManager;
use NetFusion\Core\Database\Model;
use NetFusion\Core\Forms\Fields\CheckboxField;
use NetFusion\Core\Forms\Fields\DateField;
use NetFusion\Core\Forms\Fields\FileField;
use NetFusion\Core\Forms\Fields\JsonField;
use NetFusion\Core\Forms\Fields\NumberField;
use NetFusion\Core\Forms\Fields\SelectField;
use NetFusion\Core\Forms\Fields\TextareaField;
use NetFusion\Core\Forms\Fields\TextField;
use NetFusion\Core\Forms\Form;
use NetFusion\Core\Forms\FormBuilder;
use NetFusion\Core\Forms\FormSchema;
use NetFusion\Core\Forms\Traits\HasCustomFields;

$basePath = realpath(__DIR__ . '/..');
$app      = new Application($basePath);

$sqlitePath = __DIR__ . '/test_phase35.sqlite';
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}
touch($sqlitePath);

$app->make(Config::class)->set('database.default', 'sqlite');
$app->make(Config::class)->set('database.connections.sqlite.database', $sqlitePath);

/** @var DatabaseManager $db */
$db   = $app->make(DatabaseManager::class);
$conn = $db->connection('sqlite');

// Run migrations
$migration = require __DIR__ . '/../database/migrations/2026_09_18_000018_create_custom_fields_tables.php';
$migration->up($conn);

$passed = 0;
$failed = 0;
$total  = 0;

function t(bool $cond, string $name): void
{
    global $passed, $failed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  \033[32m✔\033[0m {$name}\n";
    } else {
        $failed++;
        echo "  \033[31m✖\033[0m {$name}\n";
    }
}

echo "\n=================================================================\n";
echo "  NetFusion Phase 35 — Dynamic Form Engine & Custom Fields Tests\n";
echo "=================================================================\n\n";

// =============================================================================
// Section 1: Field Classes & Rendering
// =============================================================================
echo "\033[36m[Section 1: Field Types & HTML Rendering]\033[0m\n";

$text = TextField::make('username', 'Username')->placeholder('Enter username')->rules('required|min:3');
t($text->getName() === 'username', 'TextField getName() returns "username"');
t($text->getLabel() === 'Username', 'TextField getLabel() returns "Username"');
t($text->isRequired() === true, 'TextField isRequired() evaluated from rules');
t(str_contains($text->render(), 'type="text"') && str_contains($text->render(), 'name="username"'), 'TextField renders standard HTML input');

$email = TextField::make('user_email')->email();
t($email->getType() === 'text' && str_contains($email->render(), 'type="email"'), 'TextField::email() sets type="email"');

$num = NumberField::make('age', 'Age')->min(18)->max(99)->step(1);
t($num->getType() === 'number', 'NumberField getType() returns "number"');
t(str_contains($num->render(), 'min="18"') && str_contains($num->render(), 'max="99"'), 'NumberField renders min/max/step attributes');

$select = SelectField::make('country', 'Country')
    ->options(['BD' => 'Bangladesh', 'US' => 'United States', 'CA' => 'Canada'])
    ->emptyOption('Select Country')
    ->default('BD');
t($select->getType() === 'select', 'SelectField getType() returns "select"');
t($select->getValue() === 'BD', 'SelectField returns default value');
$selectHtml = $select->render();
t(str_contains($selectHtml, '<option value="BD" selected>Bangladesh</option>'), 'SelectField renders selected option correctly');

$multiSelect = SelectField::make('roles', 'Roles')
    ->options(['admin' => 'Administrator', 'editor' => 'Editor', 'viewer' => 'Viewer'])
    ->multiple()
    ->setValue(['admin', 'editor']);
t(str_contains($multiSelect->render(), 'multiple') && str_contains($multiSelect->render(), 'name="roles[]"'), 'SelectField::multiple() renders multiple select');

$textarea = TextareaField::make('bio', 'Biography')->rows(6)->default('Hello World');
t(str_contains($textarea->render(), 'rows="6"') && str_contains($textarea->render(), 'Hello World'), 'TextareaField renders rows and inner text');

$date = DateField::make('birthday', 'Birthday');
t(str_contains($date->render(), 'type="date"'), 'DateField renders type="date"');

$dateTime = DateField::make('event_at', 'Event Time')->withTime();
t(str_contains($dateTime->render(), 'type="datetime-local"'), 'DateField::withTime() renders type="datetime-local"');

$checkbox = CheckboxField::make('terms', 'Agree to Terms')->setValue(true);
t($checkbox->isChecked() === true, 'CheckboxField isChecked() returns true');
t(str_contains($checkbox->render(), 'checked'), 'CheckboxField renders checked attribute');

$file = FileField::make('avatar', 'Profile Picture')->accept('image/*');
t(str_contains($file->render(), 'type="file"') && str_contains($file->render(), 'accept="image/*"'), 'FileField renders accept attribute');

$json = JsonField::make('settings', 'User Settings')->default(['theme' => 'dark', 'notifications' => true]);
t($json->getType() === 'json', 'JsonField getType() returns "json"');
t(str_contains($json->render(), 'theme') && str_contains($json->render(), 'dark'), 'JsonField formats array as pretty JSON in textarea');

// =============================================================================
// Section 2: Validation Engine
// =============================================================================
echo "\n\033[36m[Section 2: Field Validation Engine]\033[0m\n";

$reqField = TextField::make('title')->rules('required');
t(count($reqField->validate('')) === 1, 'Validation: empty required field returns error');
t(count($reqField->validate('Valid Title')) === 0, 'Validation: non-empty required field passes');

$minField = TextField::make('password')->rules('min:8');
t(count($minField->validate('12345')) === 1, 'Validation: min:8 fails for short string');
t(count($minField->validate('secret123')) === 0, 'Validation: min:8 passes for 9 chars');

$emailField = TextField::make('email')->rules('email');
t(count($emailField->validate('invalid-email')) === 1, 'Validation: invalid email format fails');
t(count($emailField->validate('ashish@dotnetbd.com')) === 0, 'Validation: valid email format passes');

$numValidate = NumberField::make('score')->rules('min:10|max:100');
t(count($numValidate->validate(5)) === 1, 'Validation: numeric min:10 fails for 5');
t(count($numValidate->validate(150)) === 1, 'Validation: numeric max:100 fails for 150');
t(count($numValidate->validate(75)) === 0, 'Validation: numeric range 10-100 passes for 75');

$jsonValidate = JsonField::make('payload');
t(count($jsonValidate->validate('invalid {json')) === 1, 'Validation: malformed JSON string fails');
t(count($jsonValidate->validate('{"valid": true}')) === 0, 'Validation: valid JSON string passes');

// =============================================================================
// Section 3: Fluent FormBuilder & Form Lifecycle
// =============================================================================
echo "\n\033[36m[Section 3: FormBuilder & Form Lifecycle]\033[0m\n";

$builder = FormBuilder::make('/users/register', 'POST');
$builder->text('full_name', 'Full Name')->required();
$builder->email('email', 'Email Address')->required();
$builder->number('age', 'Age')->min(18);
$builder->select('gender', 'Gender', ['male' => 'Male', 'female' => 'Female']);
$builder->file('avatar', 'Avatar');
$builder->checkbox('agree', 'I accept terms')->required();

$form = $builder->build();
t($form instanceof Form, 'FormBuilder::build() returns Form instance');
t(count($form->getFields()) === 6, 'Form contains 6 defined fields');
t($form->hasField('full_name') && $form->hasField('avatar'), 'Form contains specific fields by name');

// Data binding & validation failure
$form->bind([
    'full_name' => 'Md. Atiquzzaman Ashish',
    'email'     => 'not-an-email',
    'age'       => 15, // Below min 18
    'gender'    => 'male',
    'agree'     => false,
]);

$isValid = $form->validate();
t($isValid === false, 'Form::validate() returns false for invalid data');
t($form->hasError('email'), 'Form detected error in email field');
t($form->hasError('age'), 'Form detected error in age field (min:18)');

// Data binding & validation success
$form->bind([
    'full_name' => 'Md. Atiquzzaman Ashish',
    'email'     => 'ashish@dotnetbd.com',
    'age'       => 30,
    'gender'    => 'male',
    'agree'     => true,
]);

$isNowValid = $form->validate();
t($isNowValid === true, 'Form::validate() returns true for valid data');
t(empty($form->getErrors()), 'Form errors empty after successful validation');
t($form->getValue('email') === 'ashish@dotnetbd.com', 'Form::getValue() returns bound field value');

// HTML Form Rendering
$renderedHtml = $form->render('Register Now');
t(str_starts_with($renderedHtml, '<form'), 'Form::render() starts with <form>');
t(str_ends_with(trim($renderedHtml), '</form>'), 'Form::render() ends with </form>');
t(str_contains($renderedHtml, 'enctype="multipart/form-data"'), 'Form with FileField automatically adds multipart enctype');
t(str_contains($renderedHtml, 'Register Now'), 'Form renders custom submit button text');

// JSON Schema export
$schemaArray = $form->toArray();
t(isset($schemaArray['fields']['full_name'], $schemaArray['fields']['email']), 'Form::toArray() exports all field schemas');
t(json_decode($form->toJson(), true) !== null, 'Form::toJson() produces valid JSON string');

// =============================================================================
// Section 4: FormSchema — Declarative Array & JSON Parsing
// =============================================================================
echo "\n\033[36m[Section 4: FormSchema — Declarative Parsing]\033[0m\n";

$rawSchema = [
    'action' => '/api/v1/contact',
    'fields' => [
        'name'    => ['type' => 'text', 'label' => 'Your Name', 'required' => true],
        'subject' => ['type' => 'text', 'label' => 'Subject', 'rules' => 'min:5'],
        'message' => ['type' => 'textarea', 'label' => 'Message', 'rows' => 5, 'required' => true],
        'priority'=> ['type' => 'select', 'label' => 'Priority', 'options' => ['low' => 'Low', 'high' => 'High'], 'default' => 'low'],
    ]
];

$parsedForm = FormSchema::fromArray($rawSchema);
t(count($parsedForm->getFields()) === 4, 'FormSchema::fromArray() creates 4 fields');
t($parsedForm->getField('priority') instanceof SelectField, 'FormSchema parsed priority as SelectField');
t($parsedForm->getField('name')->isRequired(), 'FormSchema parsed required flag');

$jsonString = json_encode($rawSchema);
$jsonForm = FormSchema::fromJson($jsonString);
t(count($jsonForm->getFields()) === 4, 'FormSchema::fromJson() creates identical Form instance');

// =============================================================================
// Section 5: Database Dynamic Custom Fields
// =============================================================================
echo "\n\033[36m[Section 5: Database Custom Field Definitions]\033[0m\n";

$schemaMgr = new FormSchema($db);

// Define custom fields for 'Lead' entity
$f1 = $schemaMgr->defineField('Lead', 'budget', 'Estimated Budget', 'number', 'min:0');
$f2 = $schemaMgr->defineField('Lead', 'source', 'Lead Source', 'select', 'required', [
    'web'      => 'Website',
    'referral' => 'Referral',
    'social'   => 'Social Media',
]);
$f3 = $schemaMgr->defineField('Lead', 'notes', 'Internal Notes', 'textarea');

t($f1 > 0 && $f2 > 0 && $f3 > 0, 'FormSchema::defineField() persists custom field definitions to DB');

$leadFields = $schemaMgr->getFieldsForEntity('Lead');
t(count($leadFields) === 3, 'FormSchema::getFieldsForEntity() retrieves 3 defined fields');

$leadForm = $schemaMgr->forEntity('Lead', '/leads/save');
t(count($leadForm->getFields()) === 3, 'FormSchema::forEntity() generates Form with all DB custom fields');
t($leadForm->getField('source') instanceof SelectField, 'Database select field properly instantiated');

// Update field
$schemaMgr->defineField('Lead', 'budget', 'Updated Budget Label', 'number', 'min:100');
$updatedFields = $schemaMgr->getFieldsForEntity('Lead');
$budgetField = array_values(array_filter($updatedFields, fn($f) => $f['name'] === 'budget'))[0];
t($budgetField['label'] === 'Updated Budget Label', 'FormSchema::defineField() updates existing field on duplicate name');

// Delete field
$deleted = $schemaMgr->deleteField('Lead', 'notes');
t($deleted === true && count($schemaMgr->getFieldsForEntity('Lead')) === 2, 'FormSchema::deleteField() removes field definition');

// =============================================================================
// Section 6: HasCustomFields Trait on Models
// =============================================================================
echo "\n\033[36m[Section 6: HasCustomFields Model Trait]\033[0m\n";

$conn->statement("
    CREATE TABLE IF NOT EXISTS test_leads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL
    )
");

class TestLead extends Model
{
    use HasCustomFields;

    protected string $table = 'test_leads';
    protected array $fillable = ['id', 'name', 'email'];
    protected string $customFieldEntityType = 'Lead';
}

$conn->insert("INSERT INTO test_leads (name, email) VALUES (?, ?)", ['Acme Corp', 'contact@acme.com']);
$leadId = (int)$conn->getPdo()->lastInsertId();

$leadModel = new TestLead(['id' => $leadId, 'name' => 'Acme Corp', 'email' => 'contact@acme.com']);

// Set custom field values
$leadModel->setCustomField('budget', 50000);
$leadModel->setCustomField('source', 'referral');

t($leadModel->getCustomField('budget') == 50000, 'HasCustomFields: getCustomField("budget") returns 50000');
t($leadModel->getCustomField('source') === 'referral', 'HasCustomFields: getCustomField("source") returns "referral"');

$allLeadCustom = $leadModel->getCustomFields();
t(isset($allLeadCustom['budget'], $allLeadCustom['source']), 'HasCustomFields: getCustomFields() returns all key-values');

// Pre-bound Custom Form
$preboundForm = $leadModel->getCustomForm('/leads/update');
t($preboundForm->getValue('budget') == 50000, 'HasCustomFields: getCustomForm() pre-binds saved values');
t($preboundForm->getValue('source') === 'referral', 'HasCustomFields: getCustomForm() pre-binds select value');

// =============================================================================
// Section 7: Global helper & Container Integration
// =============================================================================
echo "\n\033[36m[Section 7: Global helper form() & Container Singletons]\033[0m\n";

t(function_exists('form'), 'Global helper form() exists');

$h1 = form();
t($h1 instanceof FormBuilder, 'form() returns FormBuilder');

$h2 = form('/action', 'PUT');
t($h2 instanceof FormBuilder && $h2->build()->renderOpen() !== '', 'form("/action", "PUT") creates builder with action');

$h3 = form(['fields' => ['title' => ['type' => 'text']]]);
t($h3 instanceof Form && $h3->hasField('title'), 'form([...]) creates Form from array schema');

$h4 = form('{"fields": {"company": {"type": "text"}}}');
t($h4 instanceof Form && $h4->hasField('company'), 'form("{...}") creates Form from JSON schema');

$cSchema = $app->make('form.schema');
t($cSchema instanceof FormSchema, '"form.schema" singleton resolved from container');

// Clean up test sqlite
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}

echo "\n=================================================================\n";
echo "  Phase 35 Results: {$passed}/{$total} Passed";
if ($failed > 0) {
    echo " | \033[31m{$failed} Failed\033[0m";
} else {
    echo " \033[32m(100%)\033[0m";
}
echo "\n=================================================================\n\n";

exit($failed > 0 ? 1 : 0);