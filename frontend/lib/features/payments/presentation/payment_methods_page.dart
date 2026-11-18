import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/page_header.dart';
import '../application/payment_methods_controller.dart';
import '../domain/payment_method.dart';

class PaymentMethodsPage extends ConsumerWidget {
  const PaymentMethodsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final methods = ref.watch(paymentMethodsControllerProvider);
    final methodsNotifier = ref.read(paymentMethodsControllerProvider.notifier);

    return Scaffold(
      body: ListView(
        padding: const EdgeInsets.fromLTRB(0, 8, 0, 32),
        children: [
          const PageHeader(
            title: 'Payment methods',
            icon: Icons.credit_card,
          ),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Top up your wallet',
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Choose a provider to add funds instantly.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.textTheme.bodyMedium?.color?.withValues(
                      alpha: 0.7,
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                _TopUpOptionCard(
                  title: 'Chapa',
                  description: 'Pay with cards or bank transfer via Chapa.',
                  accent: const Color(0xFF0FB592),
                  icon: Icons.flash_on,
                  onTap: () => _showTopUpSheet(context, provider: 'Chapa'),
                  actionLabel: 'Top up with Chapa',
                ),
                const SizedBox(height: 12),
                _TopUpOptionCard(
                  title: 'Telebirr',
                  description: 'Use your Telebirr mobile wallet in seconds.',
                  accent: const Color(0xFF1BA65C),
                  icon: Icons.phone_iphone,
                  onTap: () => _showTopUpSheet(context, provider: 'Telebirr'),
                  actionLabel: 'Top up with Telebirr',
                ),
                const SizedBox(height: 24),
                if (methods.isNotEmpty)
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Saved methods',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 12),
                      ...methods.map(
                        (method) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _PaymentCard(
                            label: method.label,
                            brandAsset: method.type == PaymentMethodType.card
                                ? Icons.credit_card
                                : Icons.account_balance_wallet_outlined,
                            maskedNumber: method.detail,
                            expiry: method.expiry,
                            colorScheme: colorScheme,
                            isDefault: method.isDefault,
                            onManage: () => _showManageMethodSheet(
                              context,
                              method,
                              methodsNotifier,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                  ),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                  onPressed: () =>
                      _showAddPaymentMethodSheet(context, methodsNotifier),
                  icon: const Icon(Icons.add),
                  label: const Text('Add payment method'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PaymentCard extends StatelessWidget {
  const _PaymentCard({
    required this.label,
    required this.brandAsset,
    required this.maskedNumber,
    required this.colorScheme,
    this.expiry,
    this.isDefault = true,
    this.onManage,
  });

  final String label;
  final IconData brandAsset;
  final String maskedNumber;
  final ColorScheme colorScheme;
  final String? expiry;
  final bool isDefault;
  final VoidCallback? onManage;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 18,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                height: 48,
                width: 48,
                decoration: BoxDecoration(
                  color: colorScheme.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(brandAsset, color: colorScheme.primary),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      maskedNumber,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.textTheme.bodyMedium?.color?.withValues(
                          alpha: 0.7,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              if (isDefault)
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: colorScheme.secondary,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    'Default',
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: colorScheme.onSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 16),
          if (expiry != null && expiry!.trim().isNotEmpty)
            Row(
              children: [
                const Icon(Icons.schedule, size: 18),
                const SizedBox(width: 8),
                Text('Expires $expiry'),
                const Spacer(),
                TextButton(
                  onPressed: onManage,
                  child: const Text('Manage'),
                ),
              ],
            )
          else
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: onManage,
                child: const Text('Manage'),
              ),
            ),
        ],
      ),
    );
  }
}

Future<void> _showAddPaymentMethodSheet(
  BuildContext context,
  PaymentMethodsController methodsNotifier,
) async {
  final formKey = GlobalKey<FormState>();
  var methodType = PaymentMethodType.card;
  var mobileProvider = 'Telebirr';
  var makeDefault = true;

  final labelController = TextEditingController();
  final last4Controller = TextEditingController();
  final expiryController = TextEditingController();
  final phoneController = TextEditingController();

  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Theme.of(context).colorScheme.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
    ),
    builder: (sheetContext) {
      final theme = Theme.of(sheetContext);
      return StatefulBuilder(
        builder: (context, setState) {
          return Padding(
            padding: EdgeInsets.only(
              left: 24,
              right: 24,
              top: 20,
              bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 24,
            ),
            child: Form(
              key: formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: theme.dividerColor,
                        borderRadius: BorderRadius.circular(999),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Add payment method',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<PaymentMethodType>(
                    value: methodType,
                    decoration: const InputDecoration(
                      labelText: 'Type',
                    ),
                    items: const [
                      DropdownMenuItem(
                        value: PaymentMethodType.card,
                        child: Text('Card'),
                      ),
                      DropdownMenuItem(
                        value: PaymentMethodType.mobileMoney,
                        child: Text('Mobile money'),
                      ),
                    ],
                    onChanged: (value) {
                      if (value == null) return;
                      setState(() => methodType = value);
                    },
                  ),
                  const SizedBox(height: 12),
                  if (methodType == PaymentMethodType.card) ...[
                    TextFormField(
                      controller: labelController,
                      decoration: const InputDecoration(
                        labelText: 'Card label',
                        hintText: 'Visa or Mastercard',
                      ),
                      validator: (value) {
                        final text = value?.trim() ?? '';
                        if (text.isEmpty) {
                          return 'Enter a card label';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: last4Controller,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(
                        labelText: 'Last 4 digits',
                        hintText: '4242',
                      ),
                      validator: (value) {
                        final text = value?.trim() ?? '';
                        if (!RegExp(r'^\d{4}$').hasMatch(text)) {
                          return 'Enter the last 4 digits';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: expiryController,
                      keyboardType: TextInputType.datetime,
                      decoration: const InputDecoration(
                        labelText: 'Expiry (MM/YY)',
                        hintText: '08/27',
                      ),
                      validator: (value) {
                        final text = value?.trim() ?? '';
                        if (text.isEmpty) return null;
                        if (!RegExp(r'^\d{2}/\d{2}$').hasMatch(text)) {
                          return 'Use MM/YY';
                        }
                        final month = int.tryParse(text.split('/').first) ?? 0;
                        if (month < 1 || month > 12) {
                          return 'Invalid month';
                        }
                        return null;
                      },
                    ),
                  ] else ...[
                    DropdownButtonFormField<String>(
                      value: mobileProvider,
                      decoration: const InputDecoration(
                        labelText: 'Provider',
                      ),
                      items: const [
                        DropdownMenuItem(
                          value: 'Telebirr',
                          child: Text('Telebirr'),
                        ),
                        DropdownMenuItem(
                          value: 'Chapa',
                          child: Text('Chapa'),
                        ),
                      ],
                      onChanged: (value) {
                        if (value == null) return;
                        setState(() => mobileProvider = value);
                      },
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: phoneController,
                      keyboardType: TextInputType.phone,
                      decoration: const InputDecoration(
                        labelText: 'Phone number',
                        hintText: '09xx xxx xxx',
                      ),
                      validator: (value) {
                        final text = value?.trim() ?? '';
                        if (text.isEmpty) {
                          return 'Enter a phone number';
                        }
                        return null;
                      },
                    ),
                  ],
                  const SizedBox(height: 12),
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    value: makeDefault,
                    onChanged: (value) => setState(() => makeDefault = value),
                    title: const Text('Set as default'),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: () async {
                        final form = formKey.currentState;
                        if (form == null || !form.validate()) return;
                        final id = DateTime.now()
                            .millisecondsSinceEpoch
                            .toString();
                        final method =
                            methodType == PaymentMethodType.card
                                ? PaymentMethod(
                                  id: id,
                                  type: methodType,
                                  label: labelController.text.trim(),
                                  detail:
                                      '**** ${last4Controller.text.trim()}',
                                  expiry: expiryController.text.trim(),
                                )
                                : PaymentMethod(
                                  id: id,
                                  type: methodType,
                                  label: mobileProvider,
                                  detail: phoneController.text.trim(),
                                );
                        await methodsNotifier.addMethod(
                          method,
                          makeDefault: makeDefault,
                        );
                        if (sheetContext.mounted) {
                          Navigator.of(sheetContext).pop();
                        }
                      },
                      child: const Text('Save method'),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      );
    },
  );

  labelController.dispose();
  last4Controller.dispose();
  expiryController.dispose();
  phoneController.dispose();
}

Future<void> _showManageMethodSheet(
  BuildContext context,
  PaymentMethod method,
  PaymentMethodsController methodsNotifier,
) async {
  await showModalBottomSheet<void>(
    context: context,
    backgroundColor: Theme.of(context).colorScheme.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
    ),
    builder: (sheetContext) {
      final theme = Theme.of(sheetContext);
      return Padding(
        padding: const EdgeInsets.fromLTRB(24, 20, 24, 28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: theme.dividerColor,
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Manage ${method.label}',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              method.expiry == null || method.expiry!.trim().isEmpty
                  ? method.detail
                  : '${method.detail} - Expires ${method.expiry}',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.textTheme.bodySmall?.color?.withValues(alpha: 0.7),
              ),
            ),
            const SizedBox(height: 20),
            if (!method.isDefault)
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () async {
                    await methodsNotifier.setDefault(method.id);
                    if (sheetContext.mounted) {
                      Navigator.of(sheetContext).pop();
                    }
                  },
                  child: const Text('Set as default'),
                ),
              ),
            if (!method.isDefault) const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () async {
                  final confirmed = await _confirmRemoveMethod(
                    sheetContext,
                    method.label,
                  );
                  if (!confirmed) return;
                  await methodsNotifier.removeMethod(method.id);
                  if (sheetContext.mounted) {
                    Navigator.of(sheetContext).pop();
                  }
                },
                child: const Text('Remove method'),
              ),
            ),
          ],
        ),
      );
    },
  );
}

Future<bool> _confirmRemoveMethod(BuildContext context, String label) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (dialogContext) {
      return AlertDialog(
        title: const Text('Remove payment method'),
        content: Text('Remove $label from your wallet?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Remove'),
          ),
        ],
      );
    },
  );
  return result ?? false;
}

class _TopUpOptionCard extends StatelessWidget {
  const _TopUpOptionCard({
    required this.title,
    required this.description,
    required this.accent,
    required this.icon,
    required this.actionLabel,
    required this.onTap,
  });

  final String title;
  final String description;
  final Color accent;
  final IconData icon;
  final String actionLabel;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        color: theme.colorScheme.surface,
        border: Border.all(color: accent.withValues(alpha: 0.2)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: accent),
              ),
              const SizedBox(width: 12),
              Text(
                title,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            description,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.7),
            ),
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: onTap,
              style: FilledButton.styleFrom(
                backgroundColor: accent,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              icon: const Icon(Icons.add_circle_outline),
              label: Text(
                actionLabel,
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

Future<void> _showTopUpSheet(
  BuildContext context, {
  required String provider,
}) async {
  final amountController = TextEditingController(text: '300');
  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Theme.of(context).colorScheme.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
    ),
    builder: (sheetContext) {
      final theme = Theme.of(sheetContext);
      final quickAmounts = [150, 300, 600, 900];
      return Padding(
        padding: EdgeInsets.only(
          left: 24,
          right: 24,
          top: 24,
          bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 24,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: theme.dividerColor,
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'Top up via $provider',
              style: theme.textTheme.titleLarge?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              '$provider will securely handle your payment. Enter the amount you would like to add to your wallet.',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.7),
              ),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: quickAmounts.map((value) {
                final isSelected = amountController.text == value.toString();
                return ChoiceChip(
                  label: Text('ETB $value'),
                  selected: isSelected,
                  onSelected: (_) {
                    amountController.text = value.toString();
                  },
                );
              }).toList(),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: amountController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Amount (ETB)',
                prefixIcon: Icon(Icons.payments_outlined),
              ),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
              onPressed: () {
                Navigator.of(sheetContext).pop();
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(
                      'Redirecting to $provider for ETB ${amountController.text}',
                    ),
                  ),
                );
              },
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: Text(
                'Continue to $provider',
                textAlign: TextAlign.center,
              ),
              ),
            ),
          ],
        ),
      );
    },
  );
  amountController.dispose();
}
