import docx
from docx.shared import Pt, Inches
from docx.enum.text import WD_ALIGN_PARAGRAPH

# 创建一个新的Document对象
doc = docx.Document()

# 设置标题
title = doc.add_heading('3. 校园论坛用户手册', level=1)
title.alignment = WD_ALIGN_PARAGRAPH.CENTER

# 3.1 系统需求
doc.add_heading('3.1 系统需求', level=2)
p = doc.add_paragraph('运行校园论坛系统需要以下环境配置：')

system_req = [
    ('Web服务器', 'Apache 2.4+ 或 Nginx 1.18+'),
    ('PHP版本', 'PHP 7.4+ 或 PHP 8.0+'),
    ('数据库', 'MySQL 5.7+ 或 MariaDB 10.3+'),
    ('Web浏览器', '最新版的Chrome、Firefox、Safari或Edge'),
    ('硬盘空间', '至少200MB用于应用程序和初始数据'),
    ('内存', '至少512MB RAM（推荐1GB以上）')
]

table = doc.add_table(rows=1, cols=2)
table.style = 'Table Grid'
hdr_cells = table.rows[0].cells
hdr_cells[0].text = '组件'
hdr_cells[1].text = '要求'

for req, spec in system_req:
    row_cells = table.add_row().cells
    row_cells[0].text = req
    row_cells[1].text = spec

doc.add_paragraph('')

# 3.2 安装步骤
doc.add_heading('3.2 安装步骤', level=2)
p = doc.add_paragraph('按照以下步骤安装并配置校园论坛系统：')

installation_steps = doc.add_paragraph()
installation_steps.add_run('1. 准备环境\n').bold = True
installation_steps.add_run('确保您的服务器满足系统需求中列出的所有条件。您可以使用XAMPP、WAMP、MAMP等集成环境包，或者手动安装各组件。\n\n')

installation_steps.add_run('2. 数据库配置\n').bold = True
installation_steps.add_run('在MySQL中创建一个新的数据库：\n')
installation_steps.add_run('   a. 登录MySQL: ').italic = True
installation_steps.add_run('mysql -u root -p\n')
installation_steps.add_run('   b. 创建数据库: ').italic = True
installation_steps.add_run('CREATE DATABASE campus_forum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n')
installation_steps.add_run('   c. 创建用户并授权: ').italic = True
installation_steps.add_run('GRANT ALL PRIVILEGES ON campus_forum.* TO \'forum_user\'@\'localhost\' IDENTIFIED BY \'your_password\';\n')
installation_steps.add_run('   d. 刷新权限: ').italic = True
installation_steps.add_run('FLUSH PRIVILEGES;\n\n')

installation_steps.add_run('3. 获取源代码\n').bold = True
installation_steps.add_run('   a. 从版本控制系统克隆代码: ').italic = True
installation_steps.add_run('git clone https://your-repository-url.git\n')
installation_steps.add_run('   b. 或下载源代码压缩包并解压到您的Web服务器目录下\n\n')

installation_steps.add_run('4. 配置数据库连接\n').bold = True
installation_steps.add_run('编辑includes/db.php文件，更新数据库连接信息：\n')
db_config = doc.add_paragraph('<?php\n// 数据库连接配置\n$host = \'127.0.0.1\';\n$username = \'forum_user\'; // 使用您创建的用户名\n$password = \'your_password\'; // 使用您设置的密码\n$database = \'campus_forum\';\n$charset = \'utf8mb4\';\n?>', style='No Spacing')
db_config.paragraph_format.space_after = Pt(12)

installation_steps.add_run('\n5. 导入数据库结构\n').bold = True
installation_steps.add_run('使用提供的SQL文件初始化数据库：\n')
installation_steps.add_run('   a. 命令行方式: ').italic = True
installation_steps.add_run('mysql -u forum_user -p campus_forum < schema.sql\n')
installation_steps.add_run('   b. 或使用phpMyAdmin等工具导入SQL文件\n\n')

installation_steps.add_run('6. 设置文件权限\n').bold = True
installation_steps.add_run('确保Web服务器用户对以下目录有写入权限：\n')
installation_steps.add_run('   chmod 755 /path/to/forum\n')
installation_steps.add_run('   chmod 644 /path/to/forum/includes/db.php\n\n')

installation_steps.add_run('7. 创建管理员账户\n').bold = True
installation_steps.add_run('访问网站并注册一个新用户，然后通过数据库手动将该用户角色更改为管理员：\n')
installation_steps.add_run('   UPDATE users SET role = \'admin\' WHERE username = \'your_admin_username\';\n\n')

installation_steps.add_run('8. 配置Web服务器\n').bold = True
installation_steps.add_run('设置Web服务器虚拟主机，将网站根目录指向论坛安装目录。')

doc.add_paragraph('')

# 3.3 基本配置
doc.add_heading('3.3 基本配置', level=2)
p = doc.add_paragraph('完成安装后，您可能需要调整以下配置：')

config_items = [
    ('网站设置', '您可以通过数据库配置表修改网站名称、描述等基本信息'),
    ('邮箱域名验证', '在functions.php中修改validateEmailDomain函数，设置允许注册的邮箱域名'),
    ('敏感词过滤', '通过管理后台的敏感词管理功能添加或删除敏感词'),
    ('帖子分类', '通过数据库直接管理或开发管理界面添加/修改论坛分类')
]

table = doc.add_table(rows=1, cols=2)
table.style = 'Table Grid'
hdr_cells = table.rows[0].cells
hdr_cells[0].text = '配置项'
hdr_cells[1].text = '说明'

for item, desc in config_items:
    row_cells = table.add_row().cells
    row_cells[0].text = item
    row_cells[1].text = desc

doc.add_paragraph('')

# 3.4 用户指南
doc.add_heading('3.4 用户指南', level=2)
doc.add_heading('3.4.1 注册与登录', level=3)
registration = doc.add_paragraph()
registration.add_run('1. 访问论坛首页，点击导航栏中的"').bold = True
registration.add_run('Register').italic = True
registration.add_run('"按钮。\n')
registration.add_run('2. 填写注册表单，包括用户名、电子邮件和密码。\n')
registration.add_run('3. 提交表单后，系统会自动创建您的账号。\n')
registration.add_run('4. 使用注册的用户名和密码登录系统。')

doc.add_heading('3.4.2 浏览论坛', level=3)
browsing = doc.add_paragraph()
browsing.add_run('1. 论坛首页显示最新的帖子和分类列表。\n')
browsing.add_run('2. 点击"').bold = True
browsing.add_run('Forum').italic = True
browsing.add_run('"查看所有帖子。\n')
browsing.add_run('3. 使用右侧栏中的分类列表按主题浏览帖子。\n')
browsing.add_run('4. 使用搜索框搜索特定内容。')

doc.add_heading('3.4.3 发布帖子', level=3)
posting = doc.add_paragraph()
posting.add_run('1. 登录后，点击"').bold = True
posting.add_run('Create Post').italic = True
posting.add_run('"按钮。\n')
posting.add_run('2. 填写帖子标题、选择分类并输入内容。\n')
posting.add_run('3. 如需匿名发布，勾选"').bold = True
posting.add_run('Post anonymously').italic = True
posting.add_run('"选项。\n')
posting.add_run('4. 点击"').bold = True
posting.add_run('Create Post').italic = True
posting.add_run('"按钮提交帖子。')

doc.add_heading('3.4.4 评论与互动', level=3)
commenting = doc.add_paragraph()
commenting.add_run('1. 打开帖子后，在底部评论区输入您的评论。\n')
commenting.add_run('2. 使用点赞按钮对帖子表示赞同。\n')
commenting.add_run('3. 点击用户名查看其个人资料。')

doc.add_heading('3.4.5 私信功能', level=3)
messaging = doc.add_paragraph()
messaging.add_run('1. 点击导航栏中的"').bold = True
messaging.add_run('Chat').italic = True
messaging.add_run('"进入聊天界面。\n')
messaging.add_run('2. 选择要交流的用户并发送消息。\n')
messaging.add_run('3. 收到新消息时，导航栏中的Chat图标会显示提醒标记。')

doc.add_heading('3.4.6 使用AI助手', level=3)
ai_assistant = doc.add_paragraph()
ai_assistant.add_run('1. 点击导航栏中的"').bold = True
ai_assistant.add_run('AI Chat').italic = True
ai_assistant.add_run('"进入AI助手对话界面。\n')
ai_assistant.add_run('2. 输入您的问题或请求，AI将自动回复。\n')
ai_assistant.add_run('3. AI助手可以帮助解答学习问题、提供信息搜索等功能。')

doc.add_heading('3.4.7 通知系统', level=3)
notifications = doc.add_paragraph()
notifications.add_run('1. 当有人回复您的帖子、点赞或私信时，您会收到通知。\n')
notifications.add_run('2. 点击导航栏中的"').bold = True
notifications.add_run('Notifications').italic = True
notifications.add_run('"图标查看所有通知。\n')
notifications.add_run('3. 未读通知会以红色标记显示。')

# 3.5 管理员功能
doc.add_heading('3.5 管理员功能', level=2)
p = doc.add_paragraph('管理员拥有额外的系统管理功能：')

admin_features = doc.add_paragraph()
admin_features.add_run('3.5.1 用户管理\n').bold = True
admin_features.add_run('• 查看所有用户信息\n')
admin_features.add_run('• 禁用/启用用户账号\n')
admin_features.add_run('• 更改用户角色\n\n')

admin_features.add_run('3.5.2 内容管理\n').bold = True
admin_features.add_run('• 查看、编辑和删除任何帖子\n')
admin_features.add_run('• 处理被举报的内容\n')
admin_features.add_run('• 管理论坛分类\n\n')

admin_features.add_run('3.5.3 敏感词管理\n').bold = True
admin_features.add_run('• 添加、编辑或删除敏感词\n')
admin_features.add_run('• 设置敏感词过滤规则\n\n')

admin_features.add_run('3.5.4 系统监控\n').bold = True
admin_features.add_run('• 查看系统活动日志\n')
admin_features.add_run('• 监控系统性能和使用情况\n')
admin_features.add_run('• 处理用户反馈和投诉')

# 3.6 故障排除
doc.add_heading('3.6 故障排除', level=2)
p = doc.add_paragraph('如果您在使用过程中遇到问题，可以尝试以下解决方法：')

troubleshooting = doc.add_paragraph()
troubleshooting.add_run('3.6.1 常见问题\n').bold = True
troubleshooting.add_run('• 登录失败：').bold = True
troubleshooting.add_run('确认用户名和密码是否正确，检查账号是否被禁用\n')
troubleshooting.add_run('• 无法发布帖子：').bold = True
troubleshooting.add_run('检查是否已登录，是否填写了所有必填字段\n')
troubleshooting.add_run('• 图片上传失败：').bold = True
troubleshooting.add_run('确认图片格式和大小是否符合要求\n')
troubleshooting.add_run('• 页面加载错误：').bold = True
troubleshooting.add_run('清除浏览器缓存或尝试使用其他浏览器\n\n')

troubleshooting.add_run('3.6.2 技术支持\n').bold = True
troubleshooting.add_run('• 如遇技术问题，请通过"Contact Staff"功能联系管理员\n')
troubleshooting.add_run('• 提供详细的问题描述和复现步骤，以便快速解决问题')

# 3.7 联系方式
doc.add_heading('3.7 联系方式', level=2)
contact = doc.add_paragraph()
contact.add_run('如需更多帮助或有建议反馈，请通过以下方式联系我们：\n\n')
contact.add_run('• 系统内反馈：').bold = True
contact.add_run('使用"Contact Staff"功能发送消息\n')
contact.add_run('• 邮件联系：').bold = True
contact.add_run('admin@example.com（技术支持）\n')
contact.add_run('• 管理员账号：').bold = True
contact.add_run('在论坛中搜索并私信管理员用户')

# 保存文档
doc.save('论坛用户手册.docx')
print("文档已保存为：论坛用户手册.docx") 