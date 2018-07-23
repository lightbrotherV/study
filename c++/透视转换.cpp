#include<iostream>
#include<string>
#include<opencv2/opencv.hpp>
using namespace std;

int main(int argc,char *argv[])
{
	// 数组声明
	CvPoint2D32f srcTri[4], dstTri[4];
	// 创建数组指针
	CvMat *warp_mat = cvCreateMat(3, 3, CV_32FC1);
	if (argc != 14){
		cout << "参数出错"<<endl;
		return 0;
	}
	string input = argv[1],output = argv[2];
	IplImage *src, *dst;
	int x0,y0,x1,y1,x2,y2,x3,y3,r,g,b;
	x0 = atoi(argv[3]);
	y0 = atoi(argv[4]);
	x1 = atoi(argv[5]);
	y1 = atoi(argv[6]);
	x2 = atoi(argv[7]);
	y2 = atoi(argv[8]);
	x3 = atoi(argv[9]);
	y3 = atoi(argv[10]);
	r = atoi(argv[10]);g = atoi(argv[11]);b = atoi(argv[12]);
	// 载入图像
	src = cvLoadImage(input.data(), CV_LOAD_IMAGE_UNCHANGED);
    // 创建输出图像
    dst = cvCreateImage(cvSize(src->width, src->height), src->depth, src->nChannels);
	dst->origin = src->origin;
	cvZero(dst);
	// 构造变换矩阵
	dstTri[0].x = x0;
	dstTri[0].y = y0;
	dstTri[1].x = src->width - x1;
	dstTri[1].y = y1;
	dstTri[2].x = x2;
	dstTri[2].y = src->height - y2;
	dstTri[3].x = src->width - x3;
	dstTri[3].y = src->height - y3;
	srcTri[0].x = 0;
	srcTri[0].y = 0;
	srcTri[1].x = src->width;
	srcTri[1].y = 0;
	srcTri[2].x = 0;
	srcTri[2].y = src->height;
	srcTri[3].x = src->width;
	srcTri[3].y = src->height;
	// 计算透视映射矩阵
	cvGetPerspectiveTransform(srcTri, dstTri, warp_mat);
	// 调用函数cvWarpPerspective（）
	CvScalar scalar(b,g,r,0);
	cvWarpPerspective(src, dst, warp_mat);


	// 保存结果图
	cvSaveImage(output.data(), dst);

	cvReleaseImage(&src);
	cvReleaseImage(&dst);
	cvReleaseMat(&warp_mat);
	cout << "OK" <<endl;
	return 0;

}


