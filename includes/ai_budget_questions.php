<?php
/**
 * AI Budget Questions - أسئلة إعداد الميزانية بواسطة الذكاء الاصطناعي
 * بناءً على قانون البلديات اللبناني رقم 118/1977
 */

class AIBudgetQuestions {

    /**
     * الحصول على أسئلة الميزانية العامة للبلدية
     */
    public static function getMunicipalBudgetQuestions() {
        return [
            [
                'id' => 'budget_type',
                'question' => 'ما نوع الميزانية التي تريد إنشاءها؟',
                'type' => 'select',
                'required' => true,
                'options' => [
                    'general' => 'ميزانية عامة للبلدية',
                    'committee' => 'ميزانية لجنة محددة'
                ]
            ],
            [
                'id' => 'fiscal_year',
                'question' => 'ما هي السنة المالية للميزانية؟',
                'type' => 'number',
                'required' => true,
                'default' => date('Y'),
                'validation' => 'numeric|min:2020|max:2100'
            ],
            [
                'id' => 'population',
                'question' => 'ما هو عدد السكان في نطاق البلدية؟',
                'type' => 'number',
                'required' => true,
                'help' => 'لتحديد حجم الميزانية المناسب'
            ],
            [
                'id' => 'last_year_budget',
                'question' => 'ما هي قيمة ميزانية العام الماضي (إن وجدت)؟',
                'type' => 'number',
                'required' => false,
                'help' => 'للمقارنة والتحليل'
            ],
            [
                'id' => 'revenue_sources',
                'question' => 'ما هي مصادر الإيرادات المتوقعة؟',
                'type' => 'checkbox',
                'required' => true,
                'options' => [
                    'municipal_fees' => 'الرسوم البلدية المباشرة (المادة 86)',
                    'municipal_fund' => 'الصندوق البلدي المستقل',
                    'state_support' => 'مساعدات الدولة',
                    'loans' => 'القروض',
                    'fines' => 'الغرامات',
                    'donations' => 'الهبات والوصايا',
                    'property_income' => 'مردود الأملاك البلدية',
                    'project_revenues' => 'إيرادات مشاريع محددة'
                ]
            ],
            [
                'id' => 'estimated_revenues',
                'question' => 'ما هو إجمالي الإيرادات المتوقعة للعام؟',
                'type' => 'number',
                'required' => true,
                'help' => 'بالليرة اللبنانية أو العملة المحددة'
            ],
            [
                'id' => 'priority_sectors',
                'question' => 'ما هي القطاعات ذات الأولوية في الإنفاق؟',
                'type' => 'checkbox',
                'required' => true,
                'options' => [
                    'infrastructure' => 'البنية التحتية (طرقات، مياه، صرف صحي)',
                    'public_services' => 'الخدمات العامة (نظافة، إنارة)',
                    'education' => 'التعليم والثقافة',
                    'health' => 'الصحة والخدمات الاجتماعية',
                    'security' => 'الأمن والحماية المدنية',
                    'environment' => 'البيئة والمساحات الخضراء',
                    'urban_planning' => 'التخطيط العمراني',
                    'economic_development' => 'التنمية الاقتصادية'
                ]
            ],
            [
                'id' => 'employees_count',
                'question' => 'كم عدد موظفي البلدية الحاليين؟',
                'type' => 'number',
                'required' => true,
                'help' => 'لحساب بند الرواتب والأجور'
            ],
            [
                'id' => 'average_salary',
                'question' => 'ما هو متوسط الراتب الشهري للموظف؟',
                'type' => 'number',
                'required' => true,
                'help' => 'لحساب إجمالي الرواتب السنوية'
            ],
            [
                'id' => 'ongoing_projects',
                'question' => 'هل توجد مشاريع قائمة تحتاج تمويل مستمر؟',
                'type' => 'textarea',
                'required' => false,
                'help' => 'اذكر المشاريع وتكاليفها التقديرية'
            ],
            [
                'id' => 'new_projects',
                'question' => 'هل تخطط لمشاريع جديدة هذا العام؟',
                'type' => 'textarea',
                'required' => false,
                'help' => 'اذكر المشاريع المخططة وتكاليفها التقديرية'
            ],
            [
                'id' => 'debt_obligations',
                'question' => 'هل توجد ديون أو التزامات مالية سابقة؟',
                'type' => 'number',
                'required' => false,
                'help' => 'المبلغ الإجمالي المتوجب سداده'
            ],
            [
                'id' => 'operational_priorities',
                'question' => 'ما هي الأولويات التشغيلية للعام القادم؟',
                'type' => 'textarea',
                'required' => true,
                'help' => 'صف الأهداف والأولويات الرئيسية'
            ]
        ];
    }

    /**
     * الحصول على أسئلة ميزانية اللجان
     */
    public static function getCommitteeBudgetQuestions() {
        return [
            [
                'id' => 'committee_name',
                'question' => 'ما اسم اللجنة؟',
                'type' => 'text',
                'required' => true
            ],
            [
                'id' => 'committee_scope',
                'question' => 'ما هو نطاق عمل اللجنة؟',
                'type' => 'textarea',
                'required' => true,
                'help' => 'صف المهام والمسؤوليات الأساسية'
            ],
            [
                'id' => 'fiscal_year',
                'question' => 'ما هي السنة المالية؟',
                'type' => 'number',
                'required' => true,
                'default' => date('Y')
            ],
            [
                'id' => 'committee_members',
                'question' => 'كم عدد أعضاء اللجنة؟',
                'type' => 'number',
                'required' => true
            ],
            [
                'id' => 'estimated_activities',
                'question' => 'ما هي الأنشطة المخططة للعام؟',
                'type' => 'textarea',
                'required' => true,
                'help' => 'اذكر الأنشطة الرئيسية والفعاليات المخططة'
            ],
            [
                'id' => 'budget_allocation',
                'question' => 'ما هي نسبة الميزانية المخصصة من الميزانية العامة؟',
                'type' => 'number',
                'required' => false,
                'help' => 'بالنسبة المئوية (0-100)'
            ],
            [
                'id' => 'specific_needs',
                'question' => 'هل توجد احتياجات خاصة أو معدات مطلوبة؟',
                'type' => 'textarea',
                'required' => false
            ],
            [
                'id' => 'external_funding',
                'question' => 'هل تتوقع أي تمويل خارجي أو منح؟',
                'type' => 'textarea',
                'required' => false,
                'help' => 'مصادر ومبالغ متوقعة'
            ]
        ];
    }

    /**
     * البنود القياسية للميزانية البلدية بناءً على القانون اللبناني
     */
    public static function getStandardBudgetItems() {
        return [
            'revenues' => [
                'REV-001' => [
                    'name' => 'الرسوم البلدية المباشرة',
                    'category' => 'إيرادات',
                    'description' => 'الرسوم المستوفاة مباشرة من المكلفين (المادة 86)',
                    'typical_percentage' => 25
                ],
                'REV-002' => [
                    'name' => 'الصندوق البلدي المستقل',
                    'category' => 'إيرادات',
                    'description' => 'التحويلات من الصندوق البلدي في وزارة الداخلية',
                    'typical_percentage' => 40
                ],
                'REV-003' => [
                    'name' => 'مساعدات وقروض',
                    'category' => 'إيرادات',
                    'description' => 'المساعدات من الدولة والقروض',
                    'typical_percentage' => 15
                ],
                'REV-004' => [
                    'name' => 'مردود الأملاك البلدية',
                    'category' => 'إيرادات',
                    'description' => 'إيرادات من ممتلكات وأملاك البلدية',
                    'typical_percentage' => 10
                ],
                'REV-005' => [
                    'name' => 'غرامات وهبات',
                    'category' => 'إيرادات',
                    'description' => 'الغرامات والهبات والوصايا',
                    'typical_percentage' => 10
                ]
            ],
            'expenses' => [
                'EXP-001' => [
                    'name' => 'رواتب وأجور',
                    'category' => 'مصاريف',
                    'description' => 'رواتب الموظفين والعمال والمستخدمين',
                    'typical_percentage' => 35
                ],
                'EXP-002' => [
                    'name' => 'الخدمات العامة',
                    'category' => 'مصاريف',
                    'description' => 'النظافة، الإنارة، صيانة الطرق',
                    'typical_percentage' => 25
                ],
                'EXP-003' => [
                    'name' => 'البنية التحتية',
                    'category' => 'مصاريف',
                    'description' => 'مشاريع البنى التحتية والتطوير',
                    'typical_percentage' => 20
                ],
                'EXP-004' => [
                    'name' => 'المصاريف الإدارية',
                    'category' => 'مصاريف',
                    'description' => 'قرطاسية، اتصالات، مصاريف مكتبية',
                    'typical_percentage' => 8
                ],
                'EXP-005' => [
                    'name' => 'الصيانة والتشغيل',
                    'category' => 'مصاريف',
                    'description' => 'صيانة المباني والمعدات',
                    'typical_percentage' => 7
                ],
                'EXP-006' => [
                    'name' => 'احتياطي طوارئ',
                    'category' => 'مصاريف',
                    'description' => 'احتياطي للطوارئ والظروف الاستثنائية',
                    'typical_percentage' => 5
                ]
            ]
        ];
    }

    /**
     * بناء prompt للذكاء الاصطناعي
     */
    public static function buildAIPrompt($answers, $budget_type = 'general') {
        $questions_data = $budget_type === 'general' ?
            self::getMunicipalBudgetQuestions() :
            self::getCommitteeBudgetQuestions();

        $standard_items = self::getStandardBudgetItems();

        $prompt = "أنت خبير في إعداد ميزانيات البلديات اللبنانية بناءً على قانون البلديات رقم 118/1977.\n\n";
        $prompt .= "المعلومات المقدمة:\n";

        foreach ($answers as $key => $value) {
            if (!empty($value)) {
                $prompt .= "- $key: $value\n";
            }
        }

        $prompt .= "\n\nالمطلوب:\n";
        $prompt .= "1. إنشاء ميزانية متكاملة تتضمن:\n";
        $prompt .= "   - بنود الإيرادات بالتفصيل\n";
        $prompt .= "   - بنود المصاريف بالتفصيل\n";
        $prompt .= "   - التوازن بين الإيرادات والمصاريف\n";
        $prompt .= "2. استخدام المعايير القياسية للميزانيات البلدية اللبنانية\n";
        $prompt .= "3. مراعاة الأولويات المذكورة\n";
        $prompt .= "4. تقديم توصيات لتحسين الكفاءة المالية\n\n";
        $prompt .= "يرجى تقديم الميزانية بصيغة JSON بالشكل التالي:\n";
        $prompt .= "{\n";
        $prompt .= '  "budget_summary": { "total_revenues": 0, "total_expenses": 0, "balance": 0 },' . "\n";
        $prompt .= '  "revenue_items": [' . "\n";
        $prompt .= '    { "code": "REV-001", "name": "اسم البند", "amount": 0, "description": "وصف" }' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "expense_items": [' . "\n";
        $prompt .= '    { "code": "EXP-001", "name": "اسم البند", "amount": 0, "description": "وصف" }' . "\n";
        $prompt .= '  ],' . "\n";
        $prompt .= '  "recommendations": ["توصية 1", "توصية 2"]' . "\n";
        $prompt .= "}\n";

        return $prompt;
    }

    /**
     * إنشاء ميزانية نموذجية (للاختبار أو عند عدم تفعيل AI)
     */
    public static function generateSampleBudget($answers, $budget_type = 'general') {
        $standard_items = self::getStandardBudgetItems();

        // حساب إجمالي الإيرادات من الإجابات
        $estimated_revenues = floatval($answers['estimated_revenues'] ?? 100000000);

        // إنشاء بنود الإيرادات
        $revenue_items = [];
        $total_revenues = 0;

        foreach ($standard_items['revenues'] as $code => $item) {
            $amount = $estimated_revenues * ($item['typical_percentage'] / 100);
            $revenue_items[] = [
                'code' => $code,
                'name' => $item['name'],
                'description' => $item['description'],
                'amount' => round($amount, 2)
            ];
            $total_revenues += $amount;
        }

        // إنشاء بنود المصاريف
        $expense_items = [];
        $total_expenses = 0;

        // حساب الرواتب بناءً على الإجابات
        $employees_count = intval($answers['employees_count'] ?? 10);
        $average_salary = floatval($answers['average_salary'] ?? 1500000);
        $annual_salaries = $employees_count * $average_salary * 12;

        // إضافة بند الرواتب
        $expense_items[] = [
            'code' => 'EXP-001',
            'name' => 'رواتب وأجور',
            'description' => "رواتب $employees_count موظف بمعدل " . number_format($average_salary) . " شهرياً",
            'amount' => round($annual_salaries, 2)
        ];
        $total_expenses += $annual_salaries;

        // باقي البنود بناءً على النسب القياسية
        $remaining_budget = $total_revenues - $annual_salaries;

        foreach ($standard_items['expenses'] as $code => $item) {
            if ($code === 'EXP-001') continue; // تخطي الرواتب (أضفناها مسبقاً)

            $amount = $remaining_budget * ($item['typical_percentage'] / 100);
            $expense_items[] = [
                'code' => $code,
                'name' => $item['name'],
                'description' => $item['description'],
                'amount' => round($amount, 2)
            ];
            $total_expenses += $amount;
        }

        // حساب الرصيد
        $balance = $total_revenues - $total_expenses;

        // التوصيات
        $recommendations = [
            'تم إنشاء هذه الميزانية بشكل تلقائي بناءً على المعايير القياسية للبلديات اللبنانية',
            'يُنصح بمراجعة جميع البنود وتعديلها حسب احتياجات البلدية الفعلية',
            'تأكد من توازن الميزانية قبل الاعتماد النهائي'
        ];

        if ($balance < 0) {
            $recommendations[] = 'تحذير: الميزانية غير متوازنة. المصاريف تتجاوز الإيرادات بمقدار ' . number_format(abs($balance));
            $recommendations[] = 'يُنصح بتقليل المصاريف أو زيادة الإيرادات';
        } else {
            $recommendations[] = 'الميزانية متوازنة مع فائض قدره ' . number_format($balance);
        }

        // إضافة توصيات بناءً على الأولويات
        if (isset($answers['priority_sectors']) && is_array($answers['priority_sectors'])) {
            $recommendations[] = 'تم مراعاة القطاعات ذات الأولوية: ' . implode(', ', $answers['priority_sectors']);
        }

        return [
            'budget_summary' => [
                'total_revenues' => round($total_revenues, 2),
                'total_expenses' => round($total_expenses, 2),
                'balance' => round($balance, 2)
            ],
            'revenue_items' => $revenue_items,
            'expense_items' => $expense_items,
            'recommendations' => $recommendations,
            'metadata' => [
                'fiscal_year' => $answers['fiscal_year'] ?? date('Y'),
                'population' => $answers['population'] ?? 'غير محدد',
                'budget_type' => $budget_type,
                'generated_at' => date('Y-m-d H:i:s'),
                'method' => 'automatic'
            ]
        ];
    }
}
?>
