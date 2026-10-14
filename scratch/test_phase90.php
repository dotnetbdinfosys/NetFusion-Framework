<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework - Phase 90 Test Suite
 * |--------------------------------------------------------------------------
 * |
 * | Unit tests for Server-Driven UI (SDUI), Dynamic Layout Component Schema
 * | & Real-Time Client Hydration Fabric.
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/Support/helpers.php';

use NetFusion\Core\Application;
use NetFusion\Core\Database\Connection;
use NetFusion\Core\SDUI\Actions\Action;
use NetFusion\Core\SDUI\Components\ButtonComponent;
use NetFusion\Core\SDUI\Components\CardComponent;
use NetFusion\Core\SDUI\Components\ColumnComponent;
use NetFusion\Core\SDUI\Components\ContainerComponent;
use NetFusion\Core\SDUI\Components\CustomComponent;
use NetFusion\Core\SDUI\Components\FormComponent;
use NetFusion\Core\SDUI\Components\GridComponent;
use NetFusion\Core\SDUI\Components\ImageComponent;
use NetFusion\Core\SDUI\Components\InputComponent;
use NetFusion\Core\SDUI\Components\ModalComponent;
use NetFusion\Core\SDUI\Components\RowComponent;
use NetFusion\Core\SDUI\Components\StackComponent;
use NetFusion\Core\SDUI\Components\TextComponent;
use NetFusion\Core\SDUI\Enums\ActionType;
use NetFusion\Core\SDUI\Enums\ComponentType;
use NetFusion\Core\SDUI\Enums\PlatformType;
use NetFusion\Core\SDUI\Exceptions\ComponentValidationException;
use NetFusion\Core\SDUI\Exceptions\SDUIException;
use NetFusion\Core\SDUI\Hydration\HydrationPayload;
use NetFusion\Core\SDUI\Hydration\PatchDiffer;
use NetFusion\Core\SDUI\Hydration\StreamPusher;
use NetFusion\Core\SDUI\Layout\LayoutEngine;
use NetFusion\Core\SDUI\Layout\Screen;
use NetFusion\Core\SDUI\Layout\ThemeContext;
use NetFusion\Core\SDUI\Rules\PersonalizationRule;
use NetFusion\Core\SDUI\SDUIManager;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        echo "\033[32m[PASS]\033[0m {$message}\n";
        $passed++;
    } else {
        echo "\033[31m[FAIL]\033[0m {$message}\n";
        $failed++;
    }
}

echo "========================================================\n";
echo " NetFusion Phase 90: Server-Driven UI (SDUI) Test Suite \n";
echo "========================================================\n\n";

// -----------------------------------------------------------------------------
// 1. Component & Action Enums
// -----------------------------------------------------------------------------
assertTest(ComponentType::CONTAINER->value === 'container', "ComponentType CONTAINER value matches");
assertTest(ComponentType::BUTTON->value === 'button', "ComponentType BUTTON value matches");
assertTest(ComponentType::MODAL->value === 'modal', "ComponentType MODAL value matches");
assertTest(ActionType::NAVIGATE->value === 'navigate', "ActionType NAVIGATE value matches");
assertTest(ActionType::API_CALL->value === 'api_call', "ActionType API_CALL value matches");
assertTest(PlatformType::FLUTTER->value === 'flutter', "PlatformType FLUTTER value matches");

// -----------------------------------------------------------------------------
// 2. Action System & Factory Builders
// -----------------------------------------------------------------------------
$navAction = Action::navigate('/checkout', replace: true, params: ['step' => 2]);
assertTest($navAction->getType() === ActionType::NAVIGATE, "Action::navigate produces NAVIGATE type");
assertTest($navAction->getParams()['url'] === '/checkout', "Action::navigate stores destination url");
assertTest($navAction->getParams()['replace'] === true, "Action::navigate stores replace flag");

$apiAction = Action::apiCall('/api/v1/cart', 'POST', ['item_id' => 101]);
assertTest($apiAction->getType() === ActionType::API_CALL, "Action::apiCall produces API_CALL type");
assertTest($apiAction->getParams()['method'] === 'POST', "Action::apiCall method capitalized");

$modalAction = Action::openModal('login_modal', ['redirect' => '/profile']);
assertTest($modalAction->getType() === ActionType::OPEN_MODAL, "Action::openModal produces OPEN_MODAL type");
assertTest($modalAction->getParams()['modalId'] === 'login_modal', "Action::openModal stores modalId");

$toastAction = Action::snackbar('Product added to cart', 'success', 2500);
assertTest($toastAction->getType() === ActionType::SNACKBAR, "Action::snackbar produces SNACKBAR type");
assertTest($toastAction->getParams()['durationMs'] === 2500, "Action::snackbar stores durationMs");

$stateAction = Action::setState('theme', 'dark');
assertTest($stateAction->getType() === ActionType::SET_STATE, "Action::setState produces SET_STATE type");
assertTest($stateAction->getParams()['key'] === 'theme', "Action::setState stores state key");

// -----------------------------------------------------------------------------
// 3. UI Component Primitives & Fluent Builders
// -----------------------------------------------------------------------------
// Container
$container = ContainerComponent::make('c1')
    ->withPadding('16px')
    ->withMargin('8px')
    ->withBackground('#FFFFFF')
    ->withBorderRadius(8);
assertTest($container->getType() === ComponentType::CONTAINER, "ContainerComponent type is CONTAINER");
assertTest($container->getStyles()['padding'] === '16px', "ContainerComponent stores padding style");
assertTest($container->getStyles()['backgroundColor'] === '#FFFFFF', "ContainerComponent stores background style");

// Text
$text = TextComponent::make('Hello NetFusion', 'h2', 't1')
    ->withColor('#333333')
    ->withWeight('bold')
    ->withAlign('center');
assertTest($text->getType() === ComponentType::TEXT, "TextComponent type is TEXT");
assertTest($text->getProp('content') === 'Hello NetFusion', "TextComponent stores text content");
assertTest($text->getStyles()['fontWeight'] === 'bold', "TextComponent stores font weight");

// Button
$btnAction = Action::navigate('/home');
$btn = ButtonComponent::make('Get Started', $btnAction, 'primary', 'btn1')
    ->withIcon('rocket', 'left')
    ->disabled(false);
assertTest($btn->getType() === ComponentType::BUTTON, "ButtonComponent type is BUTTON");
assertTest($btn->getProp('label') === 'Get Started', "ButtonComponent stores button label");
assertTest(isset($btn->getActions()['onClick']), "ButtonComponent registers onClick action");
assertTest($btn->getActions()['onClick']->getType() === ActionType::NAVIGATE, "Button onClick action matches");

// Row & Column Hierarchy
$col = ColumnComponent::make([$text, $btn], 'col1')
    ->withSpacing(12)
    ->withAlignment('center', 'center');
assertTest($col->getType() === ComponentType::COLUMN, "ColumnComponent type is COLUMN");
assertTest(count($col->getChildren()) === 2, "ColumnComponent contains 2 children");
assertTest($col->getStyles()['gap'] === 12, "ColumnComponent stores gap spacing");

$row = RowComponent::make([$col], 'row1')->withSpacing(8);
assertTest($row->getType() === ComponentType::ROW, "RowComponent type is ROW");
assertTest(count($row->getChildren()) === 1, "RowComponent nests column child");

// Card
$card = CardComponent::make('Profile Card', [$row], 'card1')
    ->withElevation(2)
    ->withSubtitle('Software Engineer');
assertTest($card->getType() === ComponentType::CARD, "CardComponent type is CARD");
assertTest($card->getProp('title') === 'Profile Card', "CardComponent stores card title");
assertTest($card->getProp('elevation') === 2, "CardComponent stores elevation");

// Form & Input
$input = InputComponent::make('email', 'Email Address', 'email', 'inp_email')
    ->withPlaceholder('user@example.com')
    ->required(true)
    ->withValidationRules(['email', 'required']);
assertTest($input->getType() === ComponentType::INPUT, "InputComponent type is INPUT");
assertTest($input->getProp('required') === true, "InputComponent stores required flag");

$formSubmitAction = Action::apiCall('/api/auth/login');
$form = FormComponent::make('login_form', '/api/auth/login', 'POST', 'form1')
    ->withSubmitAction($formSubmitAction)
    ->addChild($input);
assertTest($form->getType() === ComponentType::FORM, "FormComponent type is FORM");
assertTest($form->getProp('formId') === 'login_form', "FormComponent stores formId");
assertTest(count($form->getChildren()) === 1, "FormComponent contains input field");

// Modal
$modal = ModalComponent::make('confirm_dialog', 'Confirm Delete', [$text], 'modal1')
    ->dismissible(true);
assertTest($modal->getType() === ComponentType::MODAL, "ModalComponent type is MODAL");
assertTest($modal->getProp('dismissible') === true, "ModalComponent is dismissible");

// Image
$img = ImageComponent::make('https://example.com/logo.png', 'Logo', 'img1')
    ->withDimensions(200, 100)
    ->withFit('contain');
assertTest($img->getType() === ComponentType::IMAGE, "ImageComponent type is IMAGE");
assertTest($img->getProp('src') === 'https://example.com/logo.png', "ImageComponent stores image src");

// Custom Component
$custom = CustomComponent::make('rating_star_bar', ['stars' => 5], 'cust1');
assertTest($custom->getType() === ComponentType::CUSTOM, "CustomComponent type is CUSTOM");
assertTest($custom->getCustomName() === 'rating_star_bar', "CustomComponent custom name matches");
assertTest($custom->toArray()['customName'] === 'rating_star_bar', "CustomComponent serializes customName");

// -----------------------------------------------------------------------------
// 4. Platform Filtering & Conditional Visibility
// -----------------------------------------------------------------------------
$iosOnlyBtn = ButtonComponent::make('Apple Pay')->forPlatforms(PlatformType::IOS);
assertTest($iosOnlyBtn->isVisible(['platform' => 'ios']) === true, "Platform filter passes on iOS");
assertTest($iosOnlyBtn->isVisible(['platform' => 'android']) === false, "Platform filter rejects on Android");
assertTest($iosOnlyBtn->isVisible(['platform' => 'web']) === false, "Platform filter rejects on Web");

$adminOnlyCard = CardComponent::make('Admin Panel')
    ->visibleWhen(['role' => 'admin']);
assertTest($adminOnlyCard->isVisible(['role' => 'admin']) === true, "Conditional visibility passes for admin role");
assertTest($adminOnlyCard->isVisible(['role' => 'guest']) === false, "Conditional visibility hides for guest role");

$dynamicConditionComp = TextComponent::make('Dynamic Greeting')
    ->visibleWhen(fn($ctx) => ($ctx['hour'] ?? 0) >= 18);
assertTest($dynamicConditionComp->isVisible(['hour' => 19]) === true, "Dynamic callback rule passes for evening hour");
assertTest($dynamicConditionComp->isVisible(['hour' => 10]) === false, "Dynamic callback rule fails for morning hour");

// -----------------------------------------------------------------------------
// 5. ThemeContext & Design System Tokens
// -----------------------------------------------------------------------------
$theme = new ThemeContext(isDarkMode: false);
assertTest($theme->isDarkMode() === false, "ThemeContext identifies light mode");
assertTest($theme->getColor('primary') === '#0066CC', "ThemeContext resolves primary color");

$darkTheme = new ThemeContext(isDarkMode: true);
assertTest($darkTheme->isDarkMode() === true, "ThemeContext identifies dark mode");
assertTest($darkTheme->getColor('background') === '#121212', "Dark ThemeContext resolves dark background");

$customTheme = new ThemeContext(false, [
    'colors' => ['brand' => '#FF5500'],
]);
assertTest($customTheme->getColor('brand') === '#FF5500', "ThemeContext accepts token overrides");

// -----------------------------------------------------------------------------
// 6. Personalization Rules
// -----------------------------------------------------------------------------
$rule = PersonalizationRule::create()
    ->requireRole('vip')
    ->requirePlatform('ios', 'android')
    ->requireAttribute('tier', 'gold');

assertTest($rule->matches([
    'roles'    => ['vip', 'customer'],
    'platform' => 'ios',
    'tier'     => 'gold',
]) === true, "PersonalizationRule matches qualifying user profile");

assertTest($rule->matches([
    'roles'    => ['regular'],
    'platform' => 'ios',
    'tier'     => 'gold',
]) === false, "PersonalizationRule rejects non-matching role");

assertTest($rule->matches([
    'roles'    => ['vip'],
    'platform' => 'web',
    'tier'     => 'gold',
]) === false, "PersonalizationRule rejects non-matching platform");

// -----------------------------------------------------------------------------
// 7. Screen Layout Builder
// -----------------------------------------------------------------------------
$screen = Screen::make('screen_home', 'Home Dashboard')
    ->withAppbar(TextComponent::make('NetFusion Store', 'h1', 'appbar_title'))
    ->withBody(ColumnComponent::make([$card, $iosOnlyBtn], 'body_col'))
    ->withState(['unread_notifications' => 3])
    ->withMetadata(['auth_required' => true]);

$renderedScreen = $screen->render(['platform' => 'android']);
assertTest($renderedScreen['screenId'] === 'screen_home', "Screen rendered screenId matches");
assertTest($renderedScreen['title'] === 'Home Dashboard', "Screen rendered title matches");
assertTest(isset($renderedScreen['appbar']), "Screen contains rendered appbar");
assertTest(isset($renderedScreen['body']), "Screen contains rendered body");
assertTest($renderedScreen['state']['unread_notifications'] === 3, "Screen preserves initial state store");

// Notice $iosOnlyBtn is omitted because platform is 'android'
$bodyChildren = $renderedScreen['body']['children'];
assertTest(count($bodyChildren) === 1, "Screen automatically prunes non-visible platform components (iOS button excluded on Android)");

// -----------------------------------------------------------------------------
// 8. LayoutEngine & Personalization Injection
// -----------------------------------------------------------------------------
$layoutEngine = new LayoutEngine();
$layoutEngine->registerScreen('screen_home', $screen);
assertTest($layoutEngine->hasScreen('screen_home') === true, "LayoutEngine recognizes registered screen");

$vipBannerRule = PersonalizationRule::create()->requireRole('vip');
$layoutEngine->addPersonalization($vipBannerRule, function (Screen $s, array $ctx) {
    $s->withMetadata(['vip_bonus_active' => true]);
});

$renderedVip = $layoutEngine->render('screen_home', ['roles' => ['vip']]);
assertTest($renderedVip['metadata']['vip_bonus_active'] === true, "LayoutEngine executes dynamic personalization injection for VIP");

$renderedStandard = $layoutEngine->render('screen_home', ['roles' => ['guest']]);
assertTest(!isset($renderedStandard['metadata']['vip_bonus_active']), "LayoutEngine does not execute VIP injection for standard user");

// -----------------------------------------------------------------------------
// 9. Client Hydration Payload
// -----------------------------------------------------------------------------
$payload = HydrationPayload::create($screen, ['platform' => 'web'], 'secret-test-key');
$payloadArray = $payload->toArray();
assertTest($payloadArray['status'] === 'success', "HydrationPayload status is success");
assertTest(is_string($payload->getChecksum()) && strlen($payload->getChecksum()) === 64, "HydrationPayload produces 64-char SHA256 checksum");
assertTest(isset($payloadArray['payload']['signature']), "HydrationPayload generates HMAC signature with secret");
assertTest($payload->getTimestamp() > 0, "HydrationPayload has valid timestamp");

// -----------------------------------------------------------------------------
// 10. RFC 6902 JSON Patch Differ & Applicator
// -----------------------------------------------------------------------------
$oldTree = [
    'title'    => 'Old Title',
    'count'    => 10,
    'theme'    => ['mode' => 'light'],
    'removable' => 'delete_me',
];
$newTree = [
    'title'    => 'New Title',
    'count'    => 10,
    'theme'    => ['mode' => 'dark'],
    'added'    => 'brand_new',
];

$patches = PatchDiffer::diff($oldTree, $newTree);
assertTest(count($patches) === 4, "PatchDiffer produces 4 discrete diff operations");

$ops = array_column($patches, 'op');
assertTest(in_array('replace', $ops), "Patches include replace operation");
assertTest(in_array('remove', $ops), "Patches include remove operation");
assertTest(in_array('add', $ops), "Patches include add operation");

$patchedResult = PatchDiffer::apply($oldTree, $patches);
assertTest($patchedResult['title'] === 'New Title', "PatchDiffer::apply updates title to 'New Title'");
assertTest($patchedResult['theme']['mode'] === 'dark', "PatchDiffer::apply updates nested theme mode");
assertTest(!isset($patchedResult['removable']), "PatchDiffer::apply removes deleted key");
assertTest($patchedResult['added'] === 'brand_new', "PatchDiffer::apply adds new key");

// -----------------------------------------------------------------------------
// 11. Real-Time UI Stream Pusher (SSE & WebSocket Frames)
// -----------------------------------------------------------------------------
$sseOutput = StreamPusher::formatSse('sdui_patch', ['op' => 'replace', 'path' => '/title', 'value' => 'Live'], 'evt-001');
assertTest(str_contains($sseOutput, 'event: sdui_patch'), "StreamPusher formats SSE event header");
assertTest(str_contains($sseOutput, 'id: evt-001'), "StreamPusher formats SSE id header");
assertTest(str_contains($sseOutput, 'data: {"op":"replace"'), "StreamPusher formats SSE data payload");

$wsFrame = StreamPusher::formatWebSocketFrame('screen_home', $patches, sequence: 5);
assertTest($wsFrame['type'] === 'SDUI_PATCH', "StreamPusher formats WebSocket frame type");
assertTest($wsFrame['sequence'] === 5, "StreamPusher formats sequence counter");
assertTest($wsFrame['screenId'] === 'screen_home', "StreamPusher formats screenId");

// -----------------------------------------------------------------------------
// 12. SDUIManager Master Facade
// -----------------------------------------------------------------------------
$sduiManager = new SDUIManager(['app_secret' => 'master-key-xyz']);
$createdScreen = $sduiManager->screen('checkout_view', 'Checkout');
assertTest($createdScreen instanceof Screen, "SDUIManager::screen creates Screen instance");
assertTest($sduiManager->getEngine()->hasScreen('checkout_view'), "SDUIManager auto-registers created screen");

$sduiManager->registerComponent('badge_pill', function ($props, $id) {
    return TextComponent::make($props['text'] ?? '', 'caption', $id)->withColor('#00FF00');
});

$customPill = $sduiManager->customComponent('badge_pill', ['text' => 'NEW!']);
assertTest($customPill instanceof TextComponent, "SDUIManager custom component factory resolves custom component");
assertTest($customPill->getProp('content') === 'NEW!', "Custom component passes props");

// Schema Validation
$validSchema = ['id' => 'comp_1', 'type' => 'button', 'children' => []];
assertTest($sduiManager->validateSchema($validSchema) === true, "SDUIManager validates compliant schema");

$invalidSchemaCaught = false;
try {
    $sduiManager->validateSchema(['type' => 123]); // Missing id, non-string type
} catch (ComponentValidationException $e) {
    $invalidSchemaCaught = true;
    assertTest(count($e->getErrors()) >= 1, "ComponentValidationException contains field errors");
}
assertTest($invalidSchemaCaught, "SDUIManager throws ComponentValidationException on invalid schema");

// SSE Patch Streaming
$streamChunk = $sduiManager->streamPatch('checkout_view', ['count' => 1], ['count' => 2], 'stream-1');
assertTest(str_contains($streamChunk, 'event: sdui_patch'), "SDUIManager::streamPatch generates SSE stream chunk");

// -----------------------------------------------------------------------------
// 13. Application Container & Global Helpers
// -----------------------------------------------------------------------------
$app = new Application(__DIR__ . '/..');
assertTest($app->has('sdui') === true, "Application container contains 'sdui' binding");
assertTest($app->make('sdui') instanceof SDUIManager, "'sdui' resolves to SDUIManager instance");

assertTest(function_exists('sdui') && function_exists('sdui_screen') && function_exists('sdui_component') && function_exists('sdui_action'), "Global SDUI helpers exist");
assertTest(sdui() instanceof SDUIManager, "sdui() helper returns SDUIManager");

$helperScreen = sdui_screen('cart_page', 'Your Cart');
assertTest($helperScreen instanceof Screen, "sdui_screen() helper returns Screen instance");

$helperAction = sdui_action(ActionType::NAVIGATE, ['url' => '/shop']);
assertTest($helperAction instanceof Action, "sdui_action() helper returns Action instance");

$helperComp = sdui_component('star_rating', ['value' => 4.5]);
assertTest($helperComp instanceof CustomComponent, "sdui_component() helper returns component instance");

// -----------------------------------------------------------------------------
// 14. Database Migration
// -----------------------------------------------------------------------------
$dbConn = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
$pdo = $dbConn->getPdo();

$migration = require __DIR__ . '/../database/migrations/2026_09_28_000073_create_sdui_tables.php';
$migration->up($dbConn);

$stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('sdui_screens', 'sdui_components', 'sdui_screen_patches')");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
assertTest(count($tables) === 3, "Database migration created sdui_screens, sdui_components, sdui_screen_patches tables");

// Insert screen record into database
$pdo->prepare("
    INSERT INTO sdui_screens (id, screen_key, version, title, layout_schema, is_active)
    VALUES (?, ?, ?, ?, ?, ?)
")->execute(['scr_1', 'home_dashboard', '1.0.0', 'Home', json_encode($renderedScreen), 1]);

$queryStmt = $pdo->prepare("SELECT title FROM sdui_screens WHERE id = ?");
$queryStmt->execute(['scr_1']);
assertTest($queryStmt->fetchColumn() === 'Home', "SDUI screen record inserted and queried from database");
$queryStmt->closeCursor();
$queryStmt = null;

// Test rollback
$migration->down($dbConn);
$droppedTables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('sdui_screens', 'sdui_components', 'sdui_screen_patches')")->fetchAll(PDO::FETCH_COLUMN);
assertTest(count($droppedTables) === 0, "Database migration down drops all SDUI tables");

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n========================================================\n";
echo " Phase 90 Test Summary: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

exit($failed > 0 ? 1 : 0);
