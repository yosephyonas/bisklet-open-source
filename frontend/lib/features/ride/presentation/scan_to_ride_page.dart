import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

class ScanToRidePage extends StatefulWidget {
  const ScanToRidePage({super.key});

  @override
  State<ScanToRidePage> createState() => _ScanToRidePageState();
}

class _ScanToRidePageState extends State<ScanToRidePage>
    with WidgetsBindingObserver {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const [BarcodeFormat.qrCode],
    returnImage: false,
  );
  final TextEditingController _manualController = TextEditingController();
  final FocusNode _manualFocus = FocusNode();
  bool _hasScanned = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (_hasScanned) return;
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused) {
      _controller.stop();
    } else if (state == AppLifecycleState.resumed) {
      _controller.start();
    }
  }

  void _handleDetection(BarcodeCapture capture) {
    if (_hasScanned) return;
    final value = capture.barcodes
        .map((barcode) => barcode.rawValue?.trim())
        .firstWhere(
          (barcode) => barcode != null && barcode.isNotEmpty,
          orElse: () => null,
        );
    if (value == null) return;
    _hasScanned = true;
    _controller.stop();
    if (!mounted) return;
    Navigator.of(context).pop(value);
  }

  void _submitManualEntry() {
    if (_hasScanned) return;
    final value = _manualController.text.trim();
    if (value.isEmpty) return;
    _hasScanned = true;
    _controller.stop();
    _manualFocus.unfocus();
    if (!mounted) return;
    Navigator.of(context).pop(value);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _manualController.dispose();
    _manualFocus.dispose();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;

    return Scaffold(
      backgroundColor: colorScheme.background,
      body: LayoutBuilder(
        builder: (context, constraints) {
          final maxHeight = constraints.maxHeight;
          final panelHeight = math.min(380.0, math.max(260.0, maxHeight * 0.42));
          final frameSize = math.min(
            constraints.maxWidth * 0.72,
            maxHeight * 0.34,
          );

          return Stack(
            children: [
              Positioned.fill(
                child: MobileScanner(
                  controller: _controller,
                  onDetect: _handleDetection,
                  errorBuilder: (context, error, child) {
                    return _ScannerErrorView(error: error);
                  },
                ),
              ),
              Positioned.fill(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        colorScheme.primary.withValues(alpha: 0.12),
                        Colors.transparent,
                        Colors.black.withValues(alpha: 0.25),
                      ],
                      stops: const [0.0, 0.45, 1.0],
                    ),
                  ),
                ),
              ),
              SafeArea(
                child: Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 8,
                      ),
                      child: Row(
                        children: [
                          Text(
                            'Bike Unlock / Rental',
                            style: theme.textTheme.titleMedium?.copyWith(
                              color: colorScheme.onSurface,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const Spacer(),
                          Icon(
                            Icons.qr_code_2,
                            color: colorScheme.primary,
                          ),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          _ScannerFrame(
                            size: frameSize,
                            colorScheme: colorScheme,
                            onClose: () => Navigator.of(context).maybePop(),
                          ),
                          const SizedBox(height: 18),
                          Text(
                            'Scan QR code',
                            style: theme.textTheme.titleMedium?.copyWith(
                              color: colorScheme.onSurface,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Find the code on the handlebar',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: colorScheme.onSurface.withValues(
                                alpha: 0.7,
                              ),
                            ),
                          ),
                          const SizedBox(height: 14),
                          _LightButton(
                            controller: _controller,
                            colorScheme: colorScheme,
                          ),
                        ],
                      ),
                    ),
                    _BottomPanel(
                      height: panelHeight,
                      colorScheme: colorScheme,
                      theme: theme,
                      controller: _manualController,
                      focusNode: _manualFocus,
                      onSubmit: _submitManualEntry,
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _ScannerFrame extends StatelessWidget {
  const _ScannerFrame({
    required this.size,
    required this.colorScheme,
    required this.onClose,
  });

  final double size;
  final ColorScheme colorScheme;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final cornerSize = math.max(22.0, size * 0.16);
    final strokeWidth = math.max(3.0, size * 0.02);

    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(22),
                border: Border.all(
                  color: colorScheme.onSurface.withValues(alpha: 0.15),
                  width: 1.2,
                ),
              ),
            ),
          ),
          _FrameCorner(
            alignment: Alignment.topLeft,
            cornerSize: cornerSize,
            strokeWidth: strokeWidth,
            color: colorScheme.secondary,
          ),
          _FrameCorner(
            alignment: Alignment.topRight,
            cornerSize: cornerSize,
            strokeWidth: strokeWidth,
            color: colorScheme.secondary,
          ),
          _FrameCorner(
            alignment: Alignment.bottomLeft,
            cornerSize: cornerSize,
            strokeWidth: strokeWidth,
            color: colorScheme.secondary,
          ),
          _FrameCorner(
            alignment: Alignment.bottomRight,
            cornerSize: cornerSize,
            strokeWidth: strokeWidth,
            color: colorScheme.secondary,
          ),
          Positioned(
            left: 12,
            top: 12,
            child: _FrameAction(
              icon: Icons.close,
              colorScheme: colorScheme,
              onPressed: onClose,
            ),
          ),
          Positioned(
            right: 12,
            top: 12,
            child: _FrameAction(
              icon: Icons.help_outline,
              colorScheme: colorScheme,
              onPressed: () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Scan the QR code on the bike handlebar.'),
                  ),
                );
              },
            ),
          ),
          Align(
            alignment: Alignment.topCenter,
            child: Container(
              margin: const EdgeInsets.only(top: 8),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: colorScheme.surface.withValues(alpha: 0.85),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: colorScheme.outlineVariant.withValues(alpha: 0.6),
                ),
              ),
              child: Text(
                'Unlock Bike',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: colorScheme.onSurface,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FrameCorner extends StatelessWidget {
  const _FrameCorner({
    required this.alignment,
    required this.cornerSize,
    required this.strokeWidth,
    required this.color,
  });

  final Alignment alignment;
  final double cornerSize;
  final double strokeWidth;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final isTop = alignment.y < 0;
    final isLeft = alignment.x < 0;
    return Align(
      alignment: alignment,
      child: Container(
        width: cornerSize,
        height: cornerSize,
        decoration: BoxDecoration(
          border: Border(
            top: isTop
                ? BorderSide(color: color, width: strokeWidth)
                : BorderSide.none,
            bottom: !isTop
                ? BorderSide(color: color, width: strokeWidth)
                : BorderSide.none,
            left: isLeft
                ? BorderSide(color: color, width: strokeWidth)
                : BorderSide.none,
            right: !isLeft
                ? BorderSide(color: color, width: strokeWidth)
                : BorderSide.none,
          ),
          borderRadius: BorderRadius.only(
            topLeft: isTop && isLeft
                ? Radius.circular(8)
                : Radius.zero,
            topRight: isTop && !isLeft
                ? Radius.circular(8)
                : Radius.zero,
            bottomLeft: !isTop && isLeft
                ? Radius.circular(8)
                : Radius.zero,
            bottomRight: !isTop && !isLeft
                ? Radius.circular(8)
                : Radius.zero,
          ),
        ),
      ),
    );
  }
}

class _FrameAction extends StatelessWidget {
  const _FrameAction({
    required this.icon,
    required this.colorScheme,
    required this.onPressed,
  });

  final IconData icon;
  final ColorScheme colorScheme;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: colorScheme.surface.withValues(alpha: 0.75),
      shape: const CircleBorder(),
      child: IconButton(
        icon: Icon(icon, size: 18, color: colorScheme.onSurface),
        onPressed: onPressed,
      ),
    );
  }
}

class _LightButton extends StatelessWidget {
  const _LightButton({
    required this.controller,
    required this.colorScheme,
  });

  final MobileScannerController controller;
  final ColorScheme colorScheme;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder(
      valueListenable: controller,
      builder: (context, state, child) {
        final torchState = controller.value.torchState;
        final isOn = torchState == TorchState.on;
        return TextButton.icon(
          onPressed: controller.toggleTorch,
          style: TextButton.styleFrom(
            foregroundColor: colorScheme.onSurface,
            backgroundColor: colorScheme.surface.withValues(alpha: 0.85),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(18),
              side: BorderSide(
                color: colorScheme.outlineVariant.withValues(alpha: 0.6),
              ),
            ),
          ),
          icon: Icon(isOn ? Icons.flash_on : Icons.flash_off, size: 18),
          label: Text(isOn ? 'Light on' : 'Light'),
        );
      },
    );
  }
}

class _BottomPanel extends StatelessWidget {
  const _BottomPanel({
    required this.height,
    required this.colorScheme,
    required this.theme,
    required this.controller,
    required this.focusNode,
    required this.onSubmit,
  });

  final double height;
  final ColorScheme colorScheme;
  final ThemeData theme;
  final TextEditingController controller;
  final FocusNode focusNode;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final viewInsets = MediaQuery.of(context).viewInsets.bottom;

    return Container(
      height: height,
      padding: EdgeInsets.fromLTRB(20, 16, 20, 16 + viewInsets),
      decoration: BoxDecoration(
        color: colorScheme.surface.withValues(alpha: 0.97),
        borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.12),
            blurRadius: 18,
            offset: const Offset(0, -8),
          ),
        ],
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: controller,
                    focusNode: focusNode,
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) => onSubmit(),
                    decoration: const InputDecoration(
                      hintText: 'Enter Bike ID (e.g. 4591)',
                      contentPadding:
                          EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                SizedBox(
                  height: 46,
                  width: 46,
                  child: FilledButton(
                    onPressed: onSubmit,
                    style: FilledButton.styleFrom(
                      padding: EdgeInsets.zero,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: const Icon(Icons.arrow_forward_rounded),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: colorScheme.surfaceVariant.withValues(alpha: 0.65),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: colorScheme.outlineVariant.withValues(alpha: 0.5),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        'PRICING',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: colorScheme.secondary,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.6,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        'View Rate Card',
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: colorScheme.onSurface.withValues(alpha: 0.6),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  RichText(
                    text: TextSpan(
                      style: theme.textTheme.titleMedium?.copyWith(
                        color: colorScheme.onSurface,
                        fontWeight: FontWeight.w700,
                      ),
                      children: [
                        const TextSpan(text: '\$1.00 '),
                        TextSpan(
                          text: 'unlock',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color:
                                colorScheme.onSurface.withValues(alpha: 0.6),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const TextSpan(text: '  +  \$0.15 '),
                        TextSpan(
                          text: '/min',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color:
                                colorScheme.onSurface.withValues(alpha: 0.6),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'RIDE SAFE',
              style: theme.textTheme.labelSmall?.copyWith(
                color: colorScheme.onSurface.withValues(alpha: 0.6),
                letterSpacing: 0.7,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: _SafetyTile(
                    icon: Icons.health_and_safety,
                    title: 'Wear a',
                    subtitle: 'Helmet',
                    colorScheme: colorScheme,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _SafetyTile(
                    icon: Icons.build_circle,
                    title: 'Check',
                    subtitle: 'Brakes',
                    colorScheme: colorScheme,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _SafetyTile(
                    icon: Icons.traffic,
                    title: 'Follow',
                    subtitle: 'Rules',
                    colorScheme: colorScheme,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SafetyTile extends StatelessWidget {
  const _SafetyTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.colorScheme,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final ColorScheme colorScheme;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
      decoration: BoxDecoration(
        color: colorScheme.surfaceVariant.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: colorScheme.outlineVariant.withValues(alpha: 0.5),
        ),
      ),
      child: Column(
        children: [
          Icon(icon, size: 20, color: colorScheme.primary),
          const SizedBox(height: 6),
          Text(
            title,
            style: theme.textTheme.labelSmall?.copyWith(
              color: colorScheme.onSurface.withValues(alpha: 0.7),
              fontWeight: FontWeight.w600,
            ),
          ),
          Text(
            subtitle,
            style: theme.textTheme.labelSmall?.copyWith(
              color: colorScheme.onSurface,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _ScannerErrorView extends StatelessWidget {
  const _ScannerErrorView({required this.error});

  final MobileScannerException error;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(
          error.errorDetails?.message ??
              'Camera access is unavailable. Check permissions and try again.',
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyMedium,
        ),
      ),
    );
  }
}
