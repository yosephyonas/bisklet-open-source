import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:bisklet/core/i18n/app_i18n.dart';

import '../../auth/application/auth_controller.dart';

class OnboardingPage extends ConsumerStatefulWidget {
  const OnboardingPage({super.key});

  @override
  ConsumerState<OnboardingPage> createState() => _OnboardingPageState();
}

class _OnboardingPageState extends ConsumerState<OnboardingPage> {
  final _pageController = PageController();
  int _currentPage = 0;

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _completeOnboarding(String route) async {
    await ref.read(authControllerProvider.notifier).markOnboardingSeen();
    if (!mounted) return;
    Navigator.of(context).pushReplacementNamed(route);
  }

  void _handleNext() {
    if (_currentPage == 2) {
      _completeOnboarding('/signup');
      return;
    }

    _pageController.nextPage(
      duration: const Duration(milliseconds: 400),
      curve: Curves.easeInOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final l10n = context.l10n;
    final pages = [
      _OnboardingSlide(
        title: l10n.tr('onboarding.locate.title'),
        description: l10n.tr('onboarding.locate.desc'),
      ),
      _OnboardingSlide(
        title: l10n.tr('onboarding.scan.title'),
        description: l10n.tr('onboarding.scan.desc'),
      ),
      _OnboardingSlide(
        title: l10n.tr('onboarding.ride.title'),
        description: l10n.tr('onboarding.ride.desc'),
      ),
    ];

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          child: Column(
            children: [
              Align(
                alignment: Alignment.topRight,
                child: TextButton(
                  onPressed: () => _completeOnboarding('/signin'),
                  child: Text(l10n.tr('onboarding.skip')),
                ),
              ),
              Expanded(
                child: PageView.builder(
                  controller: _pageController,
                  itemCount: pages.length,
                  onPageChanged: (index) {
                    setState(() {
                      _currentPage = index;
                    });
                  },
                  itemBuilder: (context, index) {
                    final page = pages[index];

                    return Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Expanded(
                          child: Center(
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                color: colorScheme.primary.withValues(
                                  alpha: 0.08,
                                ),
                                shape: BoxShape.circle,
                              ),
                              child: SizedBox(
                                height: 220,
                                width: 220,
                                child: Icon(
                                  switch (index) {
                                    0 => Icons.location_on_outlined,
                                    1 => Icons.qr_code_scanner_outlined,
                                    _ => Icons.pedal_bike_outlined,
                                  },
                                  size: 120,
                                  color: colorScheme.primary,
                                ),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 32),
                        Text(
                          page.title,
                          style: theme.textTheme.headlineMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Text(
                          page.description,
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodyLarge?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant
                                .withValues(alpha: 0.7),
                          ),
                        ),
                        const SizedBox(height: 48),
                      ],
                    );
                  },
                ),
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(
                  pages.length,
                  (index) => AnimatedContainer(
                    duration: const Duration(milliseconds: 250),
                    margin: const EdgeInsets.symmetric(horizontal: 6),
                    height: 10,
                    width: _currentPage == index ? 32 : 12,
                    decoration: BoxDecoration(
                      color: _currentPage == index
                          ? colorScheme.primary
                          : colorScheme.primary.withValues(alpha: 0.3),
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _handleNext,
                  child: Text(
                    _currentPage == pages.length - 1
                        ? l10n.tr('onboarding.get_started')
                        : l10n.tr('onboarding.next'),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(l10n.tr('onboarding.has_account')),
                  TextButton(
                    onPressed: () => _completeOnboarding('/signin'),
                    child: Text(l10n.tr('onboarding.signin')),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OnboardingSlide {
  const _OnboardingSlide({required this.title, required this.description});

  final String title;
  final String description;
}
