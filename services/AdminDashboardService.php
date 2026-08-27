<?php

require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../models/Alert.php';
require_once __DIR__ . '/../models/Log.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/User.php';

final class AdminDashboardService
{
    private const MODULE_PERMISSIONS = [
        'support.manage',
        'alerts.manage',
        'users.view',
        'shop.manage',
        'orders.manage',
        'billing.manage',
        'news.manage',
        'logs.view',
        'settings.manage',
    ];

    private const DAY_LABELS = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mer',
        4 => 'Jeu',
        5 => 'Ven',
        6 => 'Sam',
        7 => 'Dim',
    ];

    public static function build(): array
    {
        $canManageSupport = currentUserCan('support.manage');
        $canManageAlerts = currentUserCan('alerts.manage');
        $canApproveUsers = currentUserCan('users.approve');
        $canViewUsers = currentUserCan('users.view');
        $canManageShop = currentUserCan('shop.manage');
        $canManageOrders = currentUserCan('orders.manage');
        $canManageBilling = currentUserCan('billing.manage');
        $canManageNews = currentUserCan('news.manage');
        $canViewLogs = currentUserCan('logs.view');

        $ticketStats = $canManageSupport ? Ticket::getDashboardStats() : [];
        $alertStats = $canManageAlerts ? Alert::getDashboardStats() : [];
        $inactiveUsers = $canApproveUsers ? User::getInactiveCount() : 0;
        $outstandingBalance = $canManageBilling ? (float)User::getSumOfNotes() : 0.0;
        $recentOrders = $canManageOrders ? Order::getRecentOrders(5) : [];
        $recentPayments = $canManageBilling ? Payment::getRecentPayments(5) : [];
        $recentLogs = $canViewLogs ? Log::getAdminLogs(null, 10) : [];
        $dailyPayments = $canManageBilling ? Payment::getCapturedDailyTotals(7) : [];

        return [
            'module_count' => self::countAccessibleModules(),
            'metrics' => self::buildMetrics(
                $ticketStats,
                $alertStats,
                $inactiveUsers,
                $outstandingBalance,
                $canManageSupport,
                $canManageAlerts,
                $canApproveUsers,
                $canManageBilling
            ),
            'priorities' => self::buildPriorities(
                $ticketStats,
                $alertStats,
                $inactiveUsers,
                $outstandingBalance,
                $canManageSupport,
                $canManageAlerts,
                $canApproveUsers,
                $canManageBilling
            ),
            'activity' => self::buildActivity($recentLogs, $recentPayments, $recentOrders),
            'finance' => self::buildFinanceSnapshot($dailyPayments, $outstandingBalance),
            'quick_actions' => self::buildQuickActions(
                $canManageShop,
                $canManageNews,
                $canViewUsers,
                $canViewLogs,
                $canManageSupport
            ),
            'can_view_finance' => $canManageBilling,
        ];
    }

    private static function countAccessibleModules(): int
    {
        return count(array_filter(
            self::MODULE_PERMISSIONS,
            static fn(string $permission): bool => currentUserCan($permission)
        ));
    }

    private static function buildMetrics(
        array $ticketStats,
        array $alertStats,
        int $inactiveUsers,
        float $outstandingBalance,
        bool $canManageSupport,
        bool $canManageAlerts,
        bool $canApproveUsers,
        bool $canManageBilling
    ): array {
        $metrics = [];

        if ($canManageSupport) {
            $metrics[] = [
                'label' => 'Tickets ouverts',
                'value' => (string)(int)($ticketStats['open_tickets'] ?? 0),
                'icon' => 'support',
                'tone' => 'sky',
                'href' => 'index.php?controller=admin&action=tickets',
            ];
        }

        if ($canManageAlerts) {
            $openAlerts = (int)($alertStats['open_alerts'] ?? 0);
            $metrics[] = [
                'label' => 'Signalements',
                'value' => (string)$openAlerts,
                'status' => $openAlerts > 0 ? 'À traiter' : 'À jour',
                'icon' => 'alert',
                'tone' => $openAlerts > 0 ? 'coral' : 'mint',
                'href' => 'index.php?controller=admin&action=alerts',
            ];
        }

        if ($canApproveUsers) {
            $metrics[] = [
                'label' => 'Inscriptions',
                'value' => (string)$inactiveUsers,
                'icon' => 'users',
                'tone' => 'gold',
                'href' => 'index.php?controller=admin&action=pendingUsers',
            ];
        }

        if ($canManageBilling) {
            $metrics[] = [
                'label' => 'Solde à encaisser',
                'value' => self::formatMoney($outstandingBalance),
                'icon' => 'payment',
                'tone' => 'mint',
                'href' => 'index.php?controller=admin&action=payments',
            ];
        }

        return $metrics;
    }

    private static function buildPriorities(
        array $ticketStats,
        array $alertStats,
        int $inactiveUsers,
        float $outstandingBalance,
        bool $canManageSupport,
        bool $canManageAlerts,
        bool $canApproveUsers,
        bool $canManageBilling
    ): array {
        $priorities = [];

        if ($canManageAlerts) {
            $alertCount = (int)($alertStats['open_alerts'] ?? 0);
            $priorities[] = [
                'label' => $alertCount > 0
                    ? self::pluralize($alertCount, 'signalement à qualifier', 'signalements à qualifier')
                    : 'Aucun signalement à qualifier',
                'status' => $alertCount > 0 ? 'Prioritaire' : 'À jour',
                'tone' => $alertCount > 0 ? 'coral' : 'mint',
                'action' => $alertCount > 0 ? 'Ouvrir' : 'Voir',
                'href' => 'index.php?controller=admin&action=alerts',
            ];
        }

        if ($canManageSupport) {
            $waitingTickets = (int)($ticketStats['awaiting_staff_tickets'] ?? $ticketStats['open_tickets'] ?? 0);
            $priorities[] = [
                'label' => $waitingTickets > 0
                    ? self::pluralize($waitingTickets, 'ticket attend une réponse', 'tickets attendent une réponse')
                    : 'Aucun ticket en attente',
                'status' => $waitingTickets > 0 ? 'Support' : 'À jour',
                'tone' => $waitingTickets > 0 ? 'sky' : 'mint',
                'action' => $waitingTickets > 0 ? 'Répondre' : 'Voir',
                'href' => 'index.php?controller=admin&action=tickets',
            ];
        }

        if ($canApproveUsers && $inactiveUsers > 0) {
            $priorities[] = [
                'label' => self::pluralize($inactiveUsers, 'inscription à valider', 'inscriptions à valider'),
                'status' => 'Validation',
                'tone' => 'gold',
                'action' => 'Consulter',
                'href' => 'index.php?controller=admin&action=pendingUsers',
            ];
        }

        if ($canManageBilling) {
            $priorities[] = [
                'label' => $outstandingBalance > 0
                    ? self::formatMoney($outstandingBalance) . ' à encaisser'
                    : 'Aucun solde à encaisser',
                'status' => $outstandingBalance > 0 ? 'Paiement' : 'À jour',
                'tone' => $outstandingBalance > 0 ? 'gold' : 'mint',
                'action' => $outstandingBalance > 0 ? 'Consulter' : 'Voir',
                'href' => 'index.php?controller=admin&action=payments',
            ];
        }

        return array_slice($priorities, 0, 4);
    }

    private static function buildActivity(array $logs, array $payments, array $orders): array
    {
        $activity = array_map(
            static fn(array $log): array => self::mapLogActivity($log),
            $logs
        );

        foreach ($payments as $payment) {
            $payer = trim((string)($payment['payer_firstname'] ?? '') . ' ' . (string)($payment['payer_lastname'] ?? ''));
            $activity[] = [
                'title' => 'Paiement enregistré',
                'description' => self::formatMoney((float)($payment['amount_paid'] ?? 0))
                    . ($payer !== '' ? ' · ' . $payer : ''),
                'timestamp' => (string)($payment['payment_date'] ?? ''),
                'icon' => 'payment',
                'tone' => 'mint',
                'href' => 'index.php?controller=admin&action=showPayment&id=' . (int)($payment['id'] ?? 0),
            ];
        }

        foreach ($orders as $order) {
            $customer = trim((string)($order['firstname'] ?? '') . ' ' . (string)($order['lastname'] ?? ''));
            $activity[] = [
                'title' => 'Commande #' . (int)($order['id'] ?? 0),
                'description' => self::formatMoney((float)($order['total_price'] ?? 0))
                    . ($customer !== '' ? ' · ' . $customer : ''),
                'timestamp' => (string)($order['created_at'] ?? ''),
                'icon' => 'orders',
                'tone' => 'sky',
                'href' => 'index.php?controller=admin&action=showOrder&id=' . (int)($order['id'] ?? 0),
            ];
        }

        usort(
            $activity,
            static fn(array $left, array $right): int => strtotime((string)$right['timestamp']) <=> strtotime((string)$left['timestamp'])
        );

        $uniqueActivity = [];
        $seen = [];

        foreach ($activity as $item) {
            $deduplicationKey = mb_strtolower((string)$item['title'] . '|' . (string)$item['description']);

            if (isset($seen[$deduplicationKey])) {
                continue;
            }

            $seen[$deduplicationKey] = true;
            $uniqueActivity[] = $item;

            if (count($uniqueActivity) === 4) {
                break;
            }
        }

        return $uniqueActivity;
    }

    private static function mapLogActivity(array $log): array
    {
        $action = mb_strtolower((string)($log['action'] ?? ''));
        $actor = trim((string)($log['firstname'] ?? '') . ' ' . (string)($log['lastname'] ?? ''));
        $actor = $actor !== '' ? $actor : trim((string)($log['username'] ?? ''));
        $actor = $actor !== '' ? $actor : 'Système';

        $activity = [
            'title' => 'Action administrative',
            'description' => 'Réalisée par ' . $actor,
            'timestamp' => (string)($log['created_at'] ?? ''),
            'icon' => 'logs',
            'tone' => 'navy',
            'href' => 'index.php?controller=admin&action=logs',
        ];

        $mappings = [
            [['alert', 'report'], 'Signalement mis à jour', 'alert', 'coral', 'index.php?controller=admin&action=alerts', 'alerts.manage'],
            [['ticket', 'support'], 'Ticket de support mis à jour', 'support', 'sky', 'index.php?controller=admin&action=tickets', 'support.manage'],
            [['payment', 'billing', 'invoice', 'refund', 'balance'], 'Opération financière enregistrée', 'payment', 'mint', 'index.php?controller=admin&action=payments', 'billing.manage'],
            [['order', 'charge'], 'Commande mise à jour', 'orders', 'sky', 'index.php?controller=admin&action=orders', 'orders.manage'],
            [['user', 'registration', 'permission', 'password'], 'Profil utilisateur mis à jour', 'users', 'gold', 'index.php?controller=admin&action=showAllUsers', 'users.view'],
            [['product', 'variant', 'inventory', 'stock', 'shop'], 'Catalogue mis à jour', 'shop', 'mint', 'index.php?controller=shop&action=manageShop', 'shop.manage'],
            [['news'], 'Actualité mise à jour', 'news', 'sky', 'index.php?controller=admin&action=news', 'news.manage'],
            [['setting', 'maintenance'], 'Réglages mis à jour', 'settings', 'navy', 'index.php?controller=admin&action=serverSettings', 'settings.manage'],
            [['security', 'login', 'lock', 'ban', 'access'], 'Sécurité mise à jour', 'shield', 'coral', 'index.php?controller=admin&action=logs', 'logs.view'],
        ];

        foreach ($mappings as [$needles, $title, $icon, $tone, $href, $permission]) {
            foreach ($needles as $needle) {
                if (str_contains($action, $needle)) {
                    $activity['title'] = $title;
                    $activity['icon'] = $icon;
                    $activity['tone'] = $tone;
                    $activity['href'] = currentUserCan($permission) ? $href : $activity['href'];
                    break 2;
                }
            }
        }

        return $activity;
    }

    private static function buildFinanceSnapshot(array $dailyPayments, float $outstandingBalance): array
    {
        $totalsByDate = [];

        foreach ($dailyPayments as $paymentDay) {
            $totalsByDate[(string)($paymentDay['payment_day'] ?? '')] = (float)($paymentDay['captured_total'] ?? 0);
        }

        $series = [];
        $capturedTotal = 0.0;
        $today = new DateTimeImmutable('today');

        for ($offset = 6; $offset >= 0; $offset--) {
            $date = $today->modify('-' . $offset . ' days');
            $dateKey = $date->format('Y-m-d');
            $amount = (float)($totalsByDate[$dateKey] ?? 0);
            $capturedTotal += $amount;
            $series[] = [
                'date' => $dateKey,
                'label' => self::DAY_LABELS[(int)$date->format('N')],
                'amount' => $amount,
            ];
        }

        return [
            'period_label' => '7 derniers jours',
            'captured_total' => $capturedTotal,
            'outstanding_total' => $outstandingBalance,
            'series' => $series,
        ];
    }

    private static function buildQuickActions(
        bool $canManageShop,
        bool $canManageNews,
        bool $canViewUsers,
        bool $canViewLogs,
        bool $canManageSupport
    ): array {
        $actions = [];

        if ($canManageShop) {
            $actions[] = [
                'label' => 'Ajouter un produit',
                'icon' => 'inventory',
                'tone' => 'mint',
                'href' => 'index.php?controller=shop&action=addProduct',
            ];
        }

        if ($canManageNews) {
            $actions[] = [
                'label' => 'Créer une actualité',
                'icon' => 'news',
                'tone' => 'sky',
                'href' => 'index.php?controller=admin&action=createNews',
            ];
        }

        if ($canViewUsers) {
            $actions[] = [
                'label' => 'Voir les utilisateurs',
                'icon' => 'users',
                'tone' => 'mint',
                'href' => 'index.php?controller=admin&action=showAllUsers',
            ];
        }

        if ($canViewLogs) {
            $actions[] = [
                'label' => 'Ouvrir le journal',
                'icon' => 'logs',
                'tone' => 'violet',
                'href' => 'index.php?controller=admin&action=logs',
            ];
        }

        if ($canManageSupport && count($actions) < 4) {
            $actions[] = [
                'label' => 'Ouvrir le support',
                'icon' => 'support',
                'tone' => 'sky',
                'href' => 'index.php?controller=admin&action=tickets',
            ];
        }

        return array_slice($actions, 0, 4);
    }

    private static function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', ' ') . ' €';
    }

    private static function pluralize(int $count, string $singular, string $plural): string
    {
        return $count . ' ' . ($count === 1 ? $singular : $plural);
    }
}
