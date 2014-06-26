=== OAuth Provider ===
Contributors: wokamoto, megumithemes
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=9S8AJCY7XB8F4&lc=JP&item_name=WordPress%20Plugins&item_number=wp%2dplugins&currency_code=JPY&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: OAuth
Requires at least: 2.8
Tested up to: 3.4.1
Stable tag: 0.5.2

WordPress �� OAuth ǧ�ڤ���ѤǤ���褦�ˤ���ץ饰����Ǥ���

== Description ==

����ϼ¸�Ū�ʥץ饰����ǡ����ʤ��� WordPress �����Ȥ� OAuth �ץ�Х����ˤ��뤳�Ȥ��Ǥ��ޤ���
���ߡ����Υץ饰������󶡤��Ƥ��뵡ǽ�ϡ��ʲ����̤�Ǥ���

* ���ץꥱ����������Ͽ (Consumer �����μ���)
* Access ������ȯ��
* ȯ�Ԥ��줿 Consumer ����, Access �ȡ��������Ѥ�����³��˼¹Ԥ���᥽�åɤ���Ͽ����
* ȯ�Ԥ��줿 Consumer ����, Access �ȡ��������Ѥ�����³������Ͽ���줿�᥽�åɤ�¹Ԥ���

**PHP5 Required.**

= Localization =
"OAuth Provider" has been translated into languages. Our thanks and appreciation must go to the following for their contributions:

* Japanese (ja) - [OKAMOTO Wataru](http://dogmap.jp/ "dogmap.jp") (plugin author)

If you have translated into your language, please let me know.

== Installation ==

1. Upload the entire `oauth-provider` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.

The control panel of OAuth Provider is in 'OAuth Provider'.

���Υץ饰�����ͭ���ˤ���Ȱʲ��� URL ���Ф��ƥ��������Ǥ���褦�ˤʤ�ޤ���
( WordPress �����Ȥ� URL �� `http://example.com/` �ξ�� )

* `http://example.com/oauth/request_token`	Request �ȡ�����ȯ���Ѥ�URL
* `http://example.com/oauth/authorize`		�桼�����ˤ�뾵ǧ�Ѥ�URL
* `http://example.com/oauth/access_token`		Access �ȡ�����ȯ���Ѥ�URL
* `http://example.com/oauth/(�᥽�å�̾)`		Consumer ������Access �ȡ��������Ѥ��� OAuth �᥽�åɤ�ƤӽФ������ URL

���Υץ饰����Ǹ��߼�������Ƥ��� OAuth �᥽�å� `sayHello` �ϡ��桼����̾���֤������δ�ñ�ʤ�ΤǤ���

`add_oauth_method($name, $method)` ����Ѥ��뤳�Ȥǡ��᥽�åɤ���Ͽ���뤳�Ȥ��Ǥ��ޤ���
���δؿ��� `add_filter()` �Τ褦�˻��Ѥ��뤳�Ȥ��Ǥ��ޤ���

�ʲ��Τ褦�ˤ��뤳�Ȥ� `sayHello` �Ȥ����᥽�åɤ��ñ���ɲä��뤳�Ȥ��Ǥ��ޤ���
`add_oauth_method('sayHello', create_function('$request, $userid, $username', 'return "Hello {$username}!";'));`

��Ͽ���줿 `sayHello` �᥽�åɤ�¹Ԥ���ˤϡ��ʲ��� URL �˥����������Ƥ���������
`http://example.com/oauth/sayHello`


== Frequently Asked Questions ==

none

